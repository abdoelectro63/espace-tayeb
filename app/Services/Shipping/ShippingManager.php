<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\ShippingCompany;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShippingManager
{
    private const VITIPS_ENDPOINT = 'https://app.vitipsexpress.com/api/client/post/colis/add-colis';
    private const EXPRESS_BATCH_ENDPOINT = 'https://expresscoursier.net/v1.0/batch/';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * Send one order to Vitips or Express Coursier, persist tracking + status when available.
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   tracking_number: ?string,
     *   provider: ?string,
     *   response?: array<string, mixed>
     * }
     */
    public function syncOrder(Order $order, int|string $shippingCompanyId): array
    {
        $company = ShippingCompany::query()->find($shippingCompanyId);

        if (! $company) {
            return [
                'success' => false,
                'message' => 'Shipping company not found.',
                'tracking_number' => null,
                'provider' => null,
            ];
        }

        $providerKey = match (true) {
            $this->isVitipsCompany($company->name) => 'vitips',
            $this->isExpressCoursierCompany($company->name) => 'express_coursier',
            default => null,
        };

        if ($providerKey === null) {
            return [
                'success' => false,
                'message' => "Unsupported shipping provider [{$company->name}].",
                'tracking_number' => null,
                'provider' => null,
            ];
        }

        try {
            $result = match ($providerKey) {
                'vitips' => $this->sendToVitips($order, $company),
                'express_coursier' => $this->sendToExpressBatch(collect([$order]), $company)['results'][$order->id]
                    ?? [
                        'code' => 'error',
                        'message' => 'Express Coursier returned no result for this order.',
                        'tracking_number' => null,
                        'response' => [],
                    ],
            };
        } catch (\Throwable $e) {
            Log::error('ShippingManager::syncOrder failed', [
                'order_id' => $order->id,
                'shipping_company_id' => $shippingCompanyId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'tracking_number' => null,
                'provider' => $providerKey,
            ];
        }

        /** @var array<string, mixed> $json */
        $json = $result['response'] ?? [];
        $tracking = $result['tracking_number'] ?? null;

        if (blank($tracking)) {
            $tracking = $this->extractTrackingFromResponse($json);
        }

        if (blank($tracking) && $providerKey === 'express_coursier') {
            $tracking = $this->extractTrackingForOrderFromExpressResponse($json, $order, 0, 1);
        }

        $apiOk = match ($providerKey) {
            'vitips' => ($result['code'] ?? '') === 'ok',
            'express_coursier' => ($result['code'] ?? '') === 'ok',
        };

        if (! $apiOk) {
            return [
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Provider API did not confirm success.'),
                'tracking_number' => null,
                'provider' => $providerKey,
                'response' => $json,
            ];
        }

        if (filled($tracking)) {
            $payload = [
                'tracking_number' => trim((string) $tracking),
                'status' => 'shipped',
                'shipping_company_id' => $company->id,
                'shipping_company' => $company->name,
            ];
            $providerStatus = $this->parseProviderStatusForOrder($json, $order, 0, 1);
            if (filled($providerStatus)) {
                $payload['shipping_provider_status'] = $providerStatus;
            }

            $order->update($payload);

            return [
                'success' => true,
                'message' => 'Order synced successfully.',
                'tracking_number' => trim((string) $tracking),
                'provider' => $providerKey,
                'response' => $json,
            ];
        }

        Log::warning('Shipment created but tracking number missing from provider response', [
            'order_id' => $order->id,
            'provider' => $providerKey,
            'response' => $json,
        ]);

        return [
            'success' => true,
            'message' => 'Shipment created but tracking number pending. Please check provider dashboard or wait for webhook.',
            'tracking_number' => null,
            'provider' => $providerKey,
            'response' => $json,
        ];
    }

    /**
     * @return array{code:string,message:string,tracking_number:?string,response:array<string,mixed>}
     */
    public function process(Order $order, int|string $shippingCompanyId): array
    {
        $company = ShippingCompany::query()->find($shippingCompanyId);

        if (! $company) {
            throw new RuntimeException('Shipping company not found.');
        }

        if (! $this->isVitipsCompany($company->name)) {
            if ($this->isExpressCoursierCompany($company->name)) {
                return $this->sendToExpressBatch(collect([$order]), $company)['results'][$order->id]
                    ?? ['code' => 'error', 'message' => 'Express Coursier unknown response.', 'tracking_number' => null, 'response' => []];
            }

            throw new RuntimeException("Shipping provider [{$company->name}] is not supported yet.");
        }

        return $this->sendToVitips($order, $company);
    }

    /**
     * @param  Collection<int,Order>  $orders
     * @return array{
     *   provider:string,
     *   results: array<int, array{code:string,message:string,tracking_number:?string,response:array<string,mixed>}>
     * }
     */
    public function processMany(Collection $orders, int|string $shippingCompanyId): array
    {
        $company = ShippingCompany::query()->find($shippingCompanyId);

        if (! $company) {
            throw new RuntimeException('Shipping company not found.');
        }

        if ($orders->isEmpty()) {
            return ['provider' => 'none', 'results' => []];
        }

        if ($this->isVitipsCompany($company->name)) {
            $results = [];

            /** @var Order $order */
            foreach ($orders as $order) {
                $results[$order->id] = $this->sendToVitips($order, $company);
            }

            return ['provider' => 'vitips', 'results' => $results];
        }

        if ($this->isExpressCoursierCompany($company->name)) {
            return $this->sendToExpressBatch($orders, $company);
        }

        throw new RuntimeException("Shipping provider [{$company->name}] is not supported yet.");
    }

    private function isVitipsCompany(string $name): bool
    {
        return str_contains(mb_strtolower($name), 'vitips');
    }

    private function isExpressCoursierCompany(string $name): bool
    {
        $normalized = str_replace([' ', '-', '_'], '', mb_strtolower($name));

        return str_contains($normalized, 'expresscoursier');
    }

    /**
     * @return array{code:string,message:string,tracking_number:?string,response:array<string,mixed>}
     */
    private function sendToVitips(Order $order, ShippingCompany $company): array
    {
        $token = $company->api_token;

        if (blank($token)) {
            throw new RuntimeException("Missing API token for shipping company [{$company->name}].");
        }

        $order->loadMissing('orderItems.product');

        $phoneRaw = (string) ($order->customer_phone ?? '');
        $cityRaw = (string) ($order->customer_city ?? $order->city ?? '');

        $payload = [
            'fullname' => (string) ($order->customer_name ?? ''),
            'phone' => $this->normalizeMoroccanPhoneForVitips($phoneRaw),
            'city' => $this->normalizeCityLabelForVitips($cityRaw),
            'address' => (string) ($order->customer_address ?? $order->shipping_address ?? ''),
            'price' => (float) ($order->total_price ?? 0),
            'product' => $order->orderItems
                ->map(fn ($item): string => (string) ($item->product?->name ?? ''))
                ->filter()
                ->implode(', '),
            'qty' => $order->orderItems
                ->map(fn ($item): string => (string) ((int) ($item->quantity ?? 0)))
                ->filter(fn (string $qty): bool => $qty !== '0')
                ->implode(', '),
            'note' => (string) ($order->notes ?? ''),
            'change' => 0,
            'openpackage' => 1,
            'from_stock' => 0,
            'try_product' => 0,
            'internal_id' => $this->buildInternalReference($order),
        ];

        foreach (['fullname', 'phone', 'city', 'price', 'product', 'qty'] as $requiredKey) {
            if (blank($payload[$requiredKey])) {
                throw new RuntimeException("Order #{$order->id}: missing required field [{$requiredKey}] for Vitips payload.");
            }
        }

        $request = $this->http
            ->acceptJson()
            ->asForm()
            ->withHeaders([
                'api-token' => (string) $token,
                'api-Token' => (string) $token,
                'Authorization' => 'Bearer '.$token,
            ])
            ->timeout(30)
            ->retry(1, 250, throw: false);

        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        $response = $request->post(self::VITIPS_ENDPOINT, $payload);
        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if (! $response->successful()) {
            Log::error('ShippingManager Vitips request failed', [
                'shipping_company_id' => $company->id,
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'code' => 'error',
                'message' => 'Vitips request failed (HTTP '.$response->status().').',
                'tracking_number' => null,
                'response' => $json,
            ];
        }

        $code = mb_strtolower((string) ($json['code'] ?? $json['status'] ?? 'ok'));
        if ($code !== 'ok') {
            Log::error('ShippingManager Vitips returned non-ok response', [
                'shipping_company_id' => $company->id,
                'order_id' => $order->id,
                'response' => $json,
            ]);
        }

        $tracking = $this->extractTrackingFromResponse($json);

        $vitipsMessage = $this->extractVitipsApiMessage($json);

        return [
            'code' => $code,
            'message' => $vitipsMessage,
            'tracking_number' => filled($tracking) ? trim((string) $tracking) : null,
            'response' => $json,
        ];
    }

    /**
     * Vitips often returns human-readable errors under `error`, not `message`.
     *
     * @param  array<string, mixed>  $json
     */
    private function extractVitipsApiMessage(array $json): string
    {
        foreach (['error', 'message', 'msg', 'erreur', 'detail'] as $key) {
            if (isset($json[$key]) && is_string($json[$key]) && filled(trim($json[$key]))) {
                return trim($json[$key]);
            }
        }

        if (isset($json['errors']) && is_array($json['errors'])) {
            $parts = [];
            foreach ($json['errors'] as $v) {
                if (is_string($v) && filled($v)) {
                    $parts[] = $v;
                } elseif (is_array($v)) {
                    foreach ($v as $vv) {
                        if (is_string($vv) && filled($vv)) {
                            $parts[] = $vv;
                        }
                    }
                }
            }
            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        return '';
    }

    /**
     * Vitips validates MA-style mobile numbers; normalize common inputs.
     */
    private function normalizeMoroccanPhoneForVitips(string $phone): string
    {
        $phone = preg_replace('/[\s\-\.\(\)]/', '', trim($phone));
        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '+212')) {
            return strlen($phone) >= 13 ? '0'.substr($phone, 4) : $phone;
        }

        if (str_starts_with($phone, '00212')) {
            return strlen($phone) >= 14 ? '0'.substr($phone, 5) : $phone;
        }

        if (str_starts_with($phone, '212') && strlen($phone) >= 12 && $phone[3] !== '0') {
            return '0'.substr($phone, 3);
        }

        if (preg_match('/^(6|7)\d{8}$/', $phone)) {
            return '0'.$phone;
        }

        return $phone;
    }

    /**
     * Trim and simplify city (Vitips often matches against a fixed list).
     */
    private function normalizeCityLabelForVitips(string $city): string
    {
        $city = trim(preg_replace('/\s+/u', ' ', $city));
        if ($city === '') {
            return '';
        }

        if (preg_match('/^(.+?)\s*\([^)]+\)\s*$/u', $city, $m)) {
            return trim($m[1]);
        }

        return $city;
    }

    /**
     * @param  Collection<int,Order>  $orders
     * @return array{
     *   provider:string,
     *   results: array<int, array{code:string,message:string,tracking_number:?string,response:array<string,mixed>}>
     * }
     */
    private function sendToExpressBatch(Collection $orders, ShippingCompany $company): array
    {
        $token = $company->api_token;
        if (blank($token)) {
            throw new RuntimeException("Missing API token for shipping company [{$company->name}].");
        }

        $storeId = $company->getRawOriginal('store_id')
            ?? config('services.expresscoursier.store_id')
            ?? env('EXPRESSCOURSIER_STORE_ID');

        if (blank($storeId)) {
            $storeId = $this->resolveExpressStoreId((string) $token);
        }

        if (blank($storeId)) {
            throw new RuntimeException("Missing store_id for shipping company [{$company->name}]. Please set Store ID in shipping company settings.");
        }

        // Persist auto-discovered store_id so next calls are immediate.
        if (blank($company->getRawOriginal('store_id'))) {
            $company->forceFill(['store_id' => $storeId])->save();
        }

        $packages = [];
        $orderIds = [];

        /** @var Order $order */
        foreach ($orders as $order) {
            $order->loadMissing('orderItems.product');

            $package = [
                'receiver_name' => (string) ($order->customer_name ?? ''),
                'address' => (string) ($order->shipping_address ?? ''),
                'city' => $this->resolveExpressCityCode((string) ($order->city ?? '')),
                'phone' => (string) ($order->customer_phone ?? ''),
                'price' => (string) ((float) ($order->total_price ?? 0)),
                'note' => (string) ($order->notes ?? ''),
                'product' => $this->buildExpressProductLabel($order),
                'internal_id' => $this->buildExpressInternalReference($order),
            ];

            foreach (['receiver_name', 'address', 'city', 'phone', 'price', 'product'] as $required) {
                if (blank($package[$required])) {
                    throw new RuntimeException("Order #{$order->id}: missing required field [{$required}] for Express Coursier.");
                }
            }

            $packages[] = $package;
            $orderIds[] = (int) $order->id;
        }

        $payload = [
            'store_id' => $storeId,
            'packages' => $packages,
        ];

        $request = $this->http
            ->acceptJson()
            ->asJson()
            ->timeout(45)
            ->retry(1, 250, throw: false);

        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        $response = $request->post(self::EXPRESS_BATCH_ENDPOINT.urlencode((string) $token), $payload);
        $json = $response->json();
        $json = is_array($json) ? $json : [];

        $results = [];
        $code = $response->successful() ? 'ok' : 'error';
        $message = (string) ($json['message'] ?? $json['msg'] ?? ($code === 'ok' ? 'Batch sent successfully.' : 'Express Coursier request failed.'));

        if (! $response->successful()) {
            Log::error('ShippingManager Express Coursier batch failed', [
                'shipping_company_id' => $company->id,
                'order_ids' => $orderIds,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        $orderCount = $orders->count();
        $orderIndex = 0;

        foreach ($orders as $order) {
            $orderId = (int) $order->id;
            $tracking = null;
            if ($orderCount === 1) {
                $tracking = $this->extractTrackingFromResponse($json);
            }
            if (blank($tracking)) {
                $tracking = $this->extractTrackingForOrderFromExpressResponse($json, $order, $orderIndex, $orderCount);
            }

            // Never call extractTrackingFromResponse($json) here when orderCount > 1: it returns the same
            // first tracking for every iteration and duplicates one code on all orders.

            $results[$orderId] = [
                'code' => $code,
                'message' => $message,
                'tracking_number' => filled($tracking) ? trim((string) $tracking) : null,
                'response' => $json,
            ];
            $orderIndex++;
        }

        return [
            'provider' => 'express_coursier',
            'results' => $results,
        ];
    }

    private function resolveExpressStoreId(string $token): ?string
    {
        $request = $this->http
            ->acceptJson()
            ->timeout(20)
            ->retry(1, 250, throw: false);

        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        $response = $request->get('https://expresscoursier.net/v1.0/stores/'.urlencode($token));
        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if (! $response->successful()) {
            Log::error('Express Coursier stores endpoint failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $candidate = $json['store_id']
            ?? $json['id']
            ?? $json['storeId']
            ?? ($json['data']['store_id'] ?? null)
            ?? ($json['data']['id'] ?? null)
            ?? ($json['data']['storeId'] ?? null);

        if (is_array($json['data'] ?? null) && array_is_list($json['data'])) {
            $first = $json['data'][0] ?? null;
            if (is_array($first)) {
                $candidate = $candidate
                    ?? ($first['store_id'] ?? null)
                    ?? ($first['storeId'] ?? null)
                    ?? ($first['id'] ?? null);
            }
        }

        if (blank($candidate)) {
            $candidate = $this->extractFirstStoreId($json);
        }

        if (blank($candidate)) {
            return null;
        }

        return trim((string) $candidate);
    }

    private function extractFirstStoreId(mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        foreach (['store_id', 'storeId', 'id'] as $key) {
            if (array_key_exists($key, $value) && filled($value[$key])) {
                return $value[$key];
            }
        }

        foreach ($value as $item) {
            $found = $this->extractFirstStoreId($item);
            if (filled($found)) {
                return $found;
            }
        }

        return null;
    }

    private function resolveExpressCityCode(string $city): string
    {
        $city = trim($city);
        if ($city === '') {
            return (string) (config('services.expresscoursier.default_city_code') ?? env('EXPRESSCOURSIER_DEFAULT_CITY_CODE', '337'));
        }

        if (preg_match('/\d+/', $city, $matches) === 1) {
            return (string) $matches[0];
        }

        return (string) (config('services.expresscoursier.default_city_code') ?? env('EXPRESSCOURSIER_DEFAULT_CITY_CODE', '337'));
    }

    private function buildExpressProductLabel(Order $order): string
    {
        $parts = $order->orderItems
            ->map(function ($item): string {
                $name = (string) ($item->product?->name ?? 'Produit');
                $qty = (int) ($item->quantity ?? 1);

                return "{$name} (Qty: {$qty})";
            })
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return 'Produit (Qty: 1)';
        }

        return $parts->implode(', ');
    }

    /**
     * Extract tracking from typical provider JSON (root or nested under `data`).
     *
     * @param  array<string, mixed>  $json
     */
    private function extractTrackingFromResponse(array $json): ?string
    {
        $keys = [
            'tracking_number',
            'tracking_code',
            'tracking',
            'tracking_id',
            'trackingNumber',
            'tracking_n',
            'code_barre',
            'numero_suivi',
            'numero_colis',
            'code_colis',
            'num_colis',
            'n_colis',
            'awb',
            'suivi',
        ];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $json)) {
                continue;
            }

            $value = $json[$key];
            if (is_scalar($value) && $value !== '' && $value !== null) {
                return trim((string) $value);
            }
        }

        if (isset($json['data']) && is_array($json['data'])) {
            $data = $json['data'];

            if (! array_is_list($data)) {
                foreach ($keys as $key) {
                    if (! array_key_exists($key, $data)) {
                        continue;
                    }

                    $value = $data[$key];
                    if (is_scalar($value) && $value !== '' && $value !== null) {
                        return trim((string) $value);
                    }
                }
            }

            if (array_is_list($data)) {
                foreach ($data as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $nested = $this->extractTrackingFromResponse($item);
                    if (filled($nested)) {
                        return $nested;
                    }
                }
            }
        }

        return $this->extractTrackingDeep($json);
    }

    /**
     * Walk nested JSON (Vitips/Express often put colis code under data.colis.*).
     *
     * @param  array<string, mixed>  $json
     */
    private function extractTrackingDeep(array $json, int $depth = 0): ?string
    {
        if ($depth > 18) {
            return null;
        }

        foreach ($json as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $keyLower = mb_strtolower((string) $key);

            if (is_scalar($value) && $value !== '' && $value !== null) {
                $str = trim((string) $value);
                if ($this->shouldSkipAsTrackingCandidate($str)) {
                    continue;
                }

                if ($this->looksLikeTrackingKeyAndValue($keyLower, $str)) {
                    return $str;
                }
            }

            if (is_array($value)) {
                $nested = $this->extractTrackingDeep($value, $depth + 1);
                if (filled($nested)) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function shouldSkipAsTrackingCandidate(string $str): bool
    {
        $lower = mb_strtolower($str);

        return in_array($lower, ['ok', 'success', 'true', 'false', 'oui', 'non'], true)
            || mb_strlen($str) > 500;
    }

    private function looksLikeTrackingKeyAndValue(string $keyLower, string $str): bool
    {
        if (preg_match('/(tracking|suivi|livraison|colis|barcode|code_barre|numero|awb|n[_\s]?colis|ref_?colis|num[_\s]?suivi)/u', $keyLower)) {
            return mb_strlen($str) >= 3;
        }

        if ($keyLower === 'code' && preg_match('/^[A-Z]{2,4}[-\s]?\d{3,}/u', $str)) {
            return true;
        }

        if (preg_match('/^[A-Z]{2,4}[-\s]?\d{4,}/u', $str) && mb_strlen($str) <= 64) {
            return true;
        }

        return false;
    }

    /**
     * Public helper for Filament / jobs: parse tracking from raw provider JSON.
     *
     * @param  array<string, mixed>  $json
     */
    public function parseTrackingFromProviderResponse(array $json): ?string
    {
        return $this->extractTrackingFromResponse($json);
    }

    /**
     * Resolve tracking for one order. For Express batch JSON shared across rows, never use the
     * first root match — match the package row for this order only.
     *
     * @param  array<string, mixed>  $json
     */
    public function parseTrackingFromProviderResponseForOrder(
        array $json,
        Order $order,
        ?int $batchIndex = null,
        ?int $batchTotal = null,
    ): ?string {
        $perPackage = $this->extractTrackingForOrderFromExpressResponse($json, $order, $batchIndex, $batchTotal);
        if (filled($perPackage)) {
            return $perPackage;
        }

        if ($this->responseLooksLikeMultiPackageBatch($json)) {
            return null;
        }

        if ($batchTotal !== null && $batchTotal > 1) {
            return null;
        }

        return $this->extractTrackingFromResponse($json);
    }

    /**
     * Human-readable status from carrier JSON (état / message / package block).
     *
     * @param  array<string, mixed>  $json
     */
    public function parseProviderStatusForOrder(
        array $json,
        Order $order,
        ?int $batchIndex = null,
        ?int $batchTotal = null,
    ): ?string {
        $perPackage = $this->extractProviderStatusForOrderFromExpressResponse($json, $order, $batchIndex, $batchTotal);
        if (filled($perPackage)) {
            return $perPackage;
        }

        if ($this->responseLooksLikeMultiPackageBatch($json)) {
            return null;
        }

        if ($batchTotal !== null && $batchTotal > 1) {
            return null;
        }

        return $this->extractProviderStatusFromResponse($json);
    }

    /**
     * JSON objects like {"0": {...}, "1": {...}} decode as associative arrays and fail array_is_list().
     *
     * @param  array<mixed>  $arr
     * @return array<int, mixed>|null
     */
    private function coerceArrayToNumericList(array $arr): ?array
    {
        if ($arr === []) {
            return null;
        }

        if (array_is_list($arr)) {
            return array_values($arr);
        }

        $indices = [];
        foreach (array_keys($arr) as $k) {
            if (is_int($k)) {
                $indices[] = $k;
            } elseif (is_string($k) && ctype_digit($k)) {
                $indices[] = (int) $k;
            } else {
                return null;
            }
        }

        sort($indices);
        $count = count($indices);
        for ($i = 0; $i < $count; $i++) {
            if ($indices[$i] !== $i) {
                return null;
            }
        }

        return array_values($arr);
    }

    /**
     * Lists of package rows from Express / Vitips batch JSON (root and nested under `data`, etc.).
     *
     * @param  array<string, mixed>  $json
     * @return array<int, array<int, mixed>>
     */
    private function collectExpressPackageLists(array $json): array
    {
        $lists = [];

        $append = function (mixed $maybeList) use (&$lists): void {
            if (! is_array($maybeList) || $maybeList === []) {
                return;
            }

            $asList = $this->coerceArrayToNumericList($maybeList);
            if ($asList !== null) {
                $lists[] = $asList;
            }
        };

        // Some APIs return a bare JSON array of package rows.
        if (array_is_list($json) && isset($json[0]) && is_array($json[0])) {
            $append($json);
        }

        foreach (['packages', 'results', 'colis', 'items', 'shipments', 'orders', 'created', 'batch'] as $key) {
            $append($json[$key] ?? null);
        }

        if (isset($json['data']) && is_array($json['data'])) {
            $data = $json['data'];
            $append($data);

            foreach (['packages', 'results', 'colis', 'items', 'list', 'rows'] as $sub) {
                $append($data[$sub] ?? null);
            }
        }

        foreach (['result', 'response', 'payload'] as $wrap) {
            if (! isset($json[$wrap]) || ! is_array($json[$wrap])) {
                continue;
            }

            $inner = $json[$wrap];
            foreach (['packages', 'results', 'colis', 'items'] as $key) {
                $append($inner[$key] ?? null);
            }

            if (isset($inner['data']) && is_array($inner['data'])) {
                $d = $inner['data'];
                $append($d);
                foreach (['packages', 'results', 'colis', 'items'] as $sub) {
                    $append($d[$sub] ?? null);
                }
            }
        }

        $this->discoverPackageListsRecursive($json, $lists, 0);

        return $this->dedupePackageLists($lists);
    }

    /**
     * Walk nested JSON for list-of-package shapes not exposed at fixed paths.
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, array<int, mixed>>  $lists
     */
    private function discoverPackageListsRecursive(array $node, array &$lists, int $depth): void
    {
        if ($depth > 10) {
            return;
        }

        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value) && count($value) >= 1 && isset($value[0]) && is_array($value[0])) {
                $first = $value[0];
                $looks = $this->looksLikePackageRow($first)
                    || (count($value) >= 2 && $this->isAssociativeArray($first));
                if ($looks) {
                    $asList = $this->coerceArrayToNumericList($value);
                    if ($asList !== null) {
                        $lists[] = $asList;
                    }
                }
            }

            if ($this->isAssociativeArray($value)) {
                $this->discoverPackageListsRecursive($value, $lists, $depth + 1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function looksLikePackageRow(array $row): bool
    {
        if ($row === []) {
            return false;
        }

        foreach (['code', 'id', 'numero', 'num'] as $k) {
            if (! array_key_exists($k, $row)) {
                continue;
            }
            $v = $row[$k];
            if (! is_scalar($v) || $v === '') {
                continue;
            }
            $s = trim((string) $v);
            if (preg_match('/^[A-Z]{2,4}[-\s]?\d{3,}/u', $s) && mb_strlen($s) <= 80) {
                return true;
            }
        }

        $keys = array_map(fn ($k): string => mb_strtolower((string) $k), array_keys($row));
        foreach ($keys as $k) {
            if (preg_match('/(internal|track|colis|suivi|barcode|parcel|ship|receiver|phone|livraison|package|ref|city|price|address|nom|client)/u', $k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $arr
     */
    private function isAssociativeArray(array $arr): bool
    {
        return $arr !== [] && ! array_is_list($arr);
    }

    /**
     * @param  array<int, array<int, mixed>>  $lists
     * @return array<int, array<int, mixed>>
     */
    private function dedupePackageLists(array $lists): array
    {
        $seen = [];
        $out = [];

        foreach ($lists as $list) {
            $first = $list[0] ?? null;
            $key = count($list).':'.(is_array($first) ? sha1(json_encode($first)) : 'x');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $list;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function responseLooksLikeMultiPackageBatch(array $json): bool
    {
        foreach ($this->collectExpressPackageLists($json) as $list) {
            if (count($list) > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flatten nested carrier rows (`colis`, `package`) so internal_id matches work.
     *
     * @param  array<string, mixed>  $pkg
     * @return array<string, mixed>
     */
    private function mergePackageRowForMatching(array $pkg): array
    {
        $merged = $pkg;

        foreach (['colis', 'package', 'parcel', 'shipment'] as $nest) {
            if (isset($pkg[$nest]) && is_array($pkg[$nest])) {
                $merged = array_merge($merged, $pkg[$nest]);
            }
        }

        return $merged;
    }

    /**
     * Extract tracking from a single package row without scanning sibling package lists.
     *
     * @param  array<string, mixed>  $pkg
     */
    private function extractTrackingFromPackageRow(array $pkg): ?string
    {
        $direct = $this->extractTrackingFromResponse($pkg);
        if (filled($direct)) {
            return $direct;
        }

        foreach (['colis', 'package', 'parcel', 'shipment', 'data'] as $nest) {
            if (! isset($pkg[$nest]) || ! is_array($pkg[$nest])) {
                continue;
            }

            $block = $pkg[$nest];
            if (array_is_list($block)) {
                continue;
            }

            $nested = $this->extractTrackingFromResponse($block);
            if (filled($nested)) {
                return $nested;
            }
        }

        return $this->extractTrackingDeep($pkg);
    }

    /**
     * @param  array<string, mixed>  $pkg
     */
    private function packageMatchesOrder(array $pkg, Order $order, string $ref, string $orderIdStr): bool
    {
        $pkg = $this->mergePackageRowForMatching($pkg);
        $numberStr = filled($order->number) ? trim((string) $order->number) : null;

        $candidates = [
            $pkg['internal_id'] ?? null,
            $pkg['internal_reference'] ?? null,
            $pkg['internalId'] ?? null,
            $pkg['internal_reference_id'] ?? null,
            $pkg['order_id'] ?? null,
            $pkg['orderId'] ?? null,
            $pkg['commande_id'] ?? null,
            $pkg['reference'] ?? null,
            $pkg['internal_code'] ?? null,
            $pkg['ref_commande'] ?? null,
            $pkg['order_reference'] ?? null,
        ];

        foreach ($candidates as $c) {
            if ($c === null || $c === '') {
                continue;
            }

            $s = trim((string) $c);
            if ($s === $ref || $s === $orderIdStr) {
                return true;
            }

            if ($numberStr !== null && $s === $numberStr) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>|null
     */
    private function findExpressPackageForOrder(
        array $json,
        Order $order,
        ?int $batchIndex = null,
        ?int $batchTotal = null,
    ): ?array {
        $ref = $this->buildExpressInternalReference($order);
        $orderIdStr = (string) $order->id;

        $lists = $this->collectExpressPackageLists($json);

        // Prefer row index: many orders share the same internal_id (same product code), so matching
        // by reference always returns the first package and duplicates tracking for every order.
        if (
            $batchIndex !== null
            && $batchTotal !== null
            && $batchTotal > 1
            && $batchIndex >= 0
            && $batchIndex < $batchTotal
        ) {
            $bestRow = null;
            $bestLen = 0;
            foreach ($lists as $list) {
                if (! isset($list[$batchIndex]) || ! is_array($list[$batchIndex])) {
                    continue;
                }
                $cnt = count($list);
                if ($cnt >= $batchTotal && $cnt >= $bestLen) {
                    $bestLen = $cnt;
                    $bestRow = $list[$batchIndex];
                }
            }

            if ($bestRow !== null) {
                return $bestRow;
            }
        }

        foreach ($lists as $list) {
            foreach ($list as $pkg) {
                if (! is_array($pkg)) {
                    continue;
                }

                if ($this->packageMatchesOrder($pkg, $order, $ref, $orderIdStr)) {
                    return $pkg;
                }
            }
        }

        if ($batchTotal === 1) {
            foreach ($lists as $list) {
                if (count($list) === 1 && isset($list[0]) && is_array($list[0])) {
                    return $list[0];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractProviderStatusFromResponse(array $json): ?string
    {
        $keys = [
            'etat', 'status', 'state', 'colis_status', 'statut', 'situation',
            'livraison_etat', 'delivery_status', 'phase', 'progress',
        ];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $json)) {
                continue;
            }

            $value = $json[$key];
            if (is_scalar($value) && $value !== '' && $value !== null) {
                return trim((string) $value);
            }
        }

        if (isset($json['colis']) && is_array($json['colis'])) {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $json['colis'])) {
                    continue;
                }

                $value = $json['colis'][$key];
                if (is_scalar($value) && $value !== '' && $value !== null) {
                    return trim((string) $value);
                }
            }
        }

        if (isset($json['data']) && is_array($json['data']) && ! array_is_list($json['data'])) {
            $data = $json['data'];
            foreach ($keys as $key) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }

                $value = $data[$key];
                if (is_scalar($value) && $value !== '' && $value !== null) {
                    return trim((string) $value);
                }
            }

            if (isset($data['colis']) && is_array($data['colis'])) {
                foreach ($keys as $key) {
                    if (! array_key_exists($key, $data['colis'])) {
                        continue;
                    }

                    $value = $data['colis'][$key];
                    if (is_scalar($value) && $value !== '' && $value !== null) {
                        return trim((string) $value);
                    }
                }
            }
        }

        if (isset($json['message']) && is_string($json['message']) && filled($json['message'])) {
            $code = mb_strtolower((string) ($json['code'] ?? ''));
            if (in_array($code, ['ok', '200', 'success', 'true'], true)) {
                return trim((string) $json['message']);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function extractProviderStatusForOrderFromExpressResponse(
        array $json,
        Order $order,
        ?int $batchIndex = null,
        ?int $batchTotal = null,
    ): ?string {
        $pkg = $this->findExpressPackageForOrder($json, $order, $batchIndex, $batchTotal);

        return $pkg !== null ? $this->extractProviderStatusFromResponse($pkg) : null;
    }

    /**
     * Match Express Coursier batch response package to this order (by internal_id / internal reference).
     *
     * @param  array<string, mixed>  $json
     */
    private function extractTrackingForOrderFromExpressResponse(
        array $json,
        Order $order,
        ?int $batchIndex = null,
        ?int $batchTotal = null,
    ): ?string {
        $pkg = $this->findExpressPackageForOrder($json, $order, $batchIndex, $batchTotal);

        return $pkg !== null ? $this->extractTrackingFromPackageRow($pkg) : null;
    }

    private function buildInternalReference(Order $order): string
    {
        $order->loadMissing('orderItems.product');

        $codes = $order->orderItems
            ->map(fn ($item): ?string => $item->product?->code)
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return 'ord-'.(string) $order->id;
        }

        if ($codes->count() === 1) {
            return (string) $codes->first();
        }

        return (string) $codes->implode('-');
    }

    /**
     * Unique per order for Express Coursier batch (product-code-only refs collide across orders).
     */
    private function buildExpressInternalReference(Order $order): string
    {
        $order->loadMissing('orderItems.product');

        $codes = $order->orderItems
            ->map(fn ($item): ?string => $item->product?->code)
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return 'ord-'.(string) $order->id;
        }

        if ($codes->count() === 1) {
            return 'ord-'.(string) $order->id.'-'.(string) $codes->first();
        }

        return 'ord-'.(string) $order->id.'-'.(string) $codes->implode('-');
    }
}
