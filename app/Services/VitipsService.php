<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VitipsService
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @return list<array{
     *   tracking_number:string,
     *   customer_name:string,
     *   status:string,
     *   status_code:string,
     *   status_note:string,
     *   city:string,
     *   total_amount:string
     * }>
     */
    public function getOrders(): array
    {
        $all = [];
        $page = 1;
        $lastPage = 1;

        do {
            $result = $this->getOrdersPage($page);
            $all = [...$all, ...$result['orders']];
            $lastPage = max(1, (int) ($result['meta']['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage);

        return $all;
    }

    /**
     * @return array{
     *   orders:list<array{
     *     tracking_number:string,
     *     customer_name:string,
     *     status:string,
     *     status_code:string,
     *     status_note:string,
     *     city:string,
     *     total_amount:string
     *   }>,
     *   meta:array{current_page:int,last_page:int,per_page:int,total:int}
     * }
     */
    public function getOrdersPage(int $page = 1): array
    {
        $token = (string) config('services.vitips.token');
        $baseUrl = rtrim((string) config('services.vitips.base_url'), '/');
        $page = max(1, $page);

        if ($token === '') {
            throw new RuntimeException('Vitips token is missing. Set VITIPS_TOKEN in .env');
        }

        $endpoints = [
            // Confirmed endpoint for all colis.
            'https://app.vitipsexpress.com/api/client/colis/list-colis',
            // Requested/expected variants fallback.
            $baseUrl.'/orders',
            'https://app.vitipsexpress.com/api/client/orders',
            // Official docs pickup list fallback.
            'https://app.vitipsexpress.com/api/client/colis/list-colis-ramassage/',
        ];
        $lastError = 'Unknown Vitips API error.';
        foreach ($endpoints as $endpoint) {
            try {
                $response = $this->request($endpoint, $token, ['page' => $page]);
            } catch (Throwable $e) {
                $lastError = 'Vitips API request exception: '.$e->getMessage();
                Log::error('Vitips API request exception', [
                    'endpoint' => $endpoint,
                    'exception' => $e->getMessage(),
                ]);
                continue;
            }

            if (! $response->successful()) {
                $lastError = sprintf(
                    'Vitips API failed with status %d: %s',
                    $response->status(),
                    (string) $response->body()
                );
                Log::error('Vitips API non-success response', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => (string) $response->body(),
                ]);
                continue;
            }

            $decoded = $response->json();
            $orders = $this->extractOrders($decoded);
            if ($orders !== []) {
                return [
                    'orders' => $orders,
                    'meta' => $this->extractMeta($decoded, count($orders), $page),
                ];
            }

            Log::error('Vitips API returned empty or unsupported payload', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);
        }

        throw new RuntimeException($lastError);
    }

    private function request(string $url, string $token, array $query = []): Response
    {
        $request = $this->http
            ->acceptJson()
            ->withToken($token)
            ->withHeaders([
                'api-Token' => $token,
                'Authorization' => 'Bearer '.$token,
            ])
            ->timeout(20)
            ->retry(1, 250, throw: false);

        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        return $request->get($url, $query);
    }

    /**
     * @param  mixed  $payload
     * @return list<array{
     *   tracking_number:string,
     *   customer_name:string,
     *   status:string,
     *   status_code:string,
     *   status_note:string,
     *   city:string,
     *   total_amount:string
     * }>
     */
    private function extractOrders(mixed $payload): array
    {
        $rows = [];

        if (is_array($payload)) {
            $rows = $payload['data']['orders'] ?? $payload['data']['colis'] ?? $payload['orders'] ?? $payload['colis'] ?? $payload['data'] ?? $payload;
        }

        if (! is_array($rows)) {
            return [];
        }

        $isList = array_is_list($rows);
        if (! $isList) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row): array {
                return [
                    'tracking_number' => $this->normalizeValue($row['tracking_number'] ?? $row['tracking'] ?? $row['code'] ?? $row['code_colis'] ?? $row['code_barre'] ?? '—'),
                    'customer_name' => $this->normalizeValue($row['customer_name'] ?? $row['fullname'] ?? $row['full_name'] ?? $row['client_name'] ?? $row['name'] ?? '—'),
                    'status' => $this->normalizeValue($row['status'] ?? $row['state'] ?? $row['etat'] ?? 'unknown'),
                    'status_code' => $this->normalizeValue($row['status_code'] ?? $row['state_code'] ?? ''),
                    'status_note' => $this->normalizeValue($row['reported_date'] ?? $row['report_date'] ?? ''),
                    'city' => $this->normalizeValue($row['city'] ?? $row['ville'] ?? '—'),
                    'total_amount' => $this->normalizeValue($row['total_amount'] ?? $row['price'] ?? $row['total'] ?? $row['montant'] ?? $row['crbt'] ?? '0'),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        if (is_array($value)) {
            $first = collect($value)->flatten()->first();
            if (is_scalar($first) || $first === null) {
                return (string) $first;
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        return '—';
    }

    /**
     * @param  mixed  $payload
     * @return array{current_page:int,last_page:int,per_page:int,total:int}
     */
    private function extractMeta(mixed $payload, int $count, int $page): array
    {
        $meta = is_array($payload) && is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        return [
            'current_page' => (int) ($meta['current_page'] ?? $page),
            'last_page' => max(1, (int) ($meta['last_page'] ?? 1)),
            'per_page' => (int) ($meta['per_page'] ?? $count),
            'total' => (int) ($meta['total'] ?? $count),
        ];
    }

    /**
     * Create a Vitips shipment and return the tracking number.
     *
     * Expects $data fields (usually derived from an Order):
     * - customer_name
     * - customer_phone
     * - shipping_address
     * - total_price
     * - product_names (concatenated)
     * - items_count (quantity)
     * - id (internal id)
     *
     * @throws \RuntimeException
     */
    public function createShipment(array $data): string
    {
        $token = (string) config('services.vitips.token');
        if ($token === '') {
            throw new RuntimeException('Vitips token is missing. Set VITIPS_TOKEN in .env');
        }

        $endpoint = 'https://app.vitipsexpress.com/api/v1/orders/create';

        $payload = [
            'nom_client' => $data['customer_name'] ?? null,
            'telephone' => $data['customer_phone'] ?? null,
            'adresse' => $data['shipping_address'] ?? null,
            'prix' => $data['total_price'] ?? null,
            'produit' => $data['product_names'] ?? null,
            'qte' => $data['items_count'] ?? null,
            'id_intern' => $data['id'] ?? null,
            'change' => 'non',
            'ouvrir' => 'oui',
        ];

        foreach (['nom_client', 'telephone', 'adresse', 'prix', 'produit', 'qte', 'id_intern'] as $key) {
            if (blank($payload[$key])) {
                throw new RuntimeException("Vitips createShipment missing required field: {$key}");
            }
        }

        $request = $this->http
            ->acceptJson()
            ->withHeaders([
                'api-Token' => $token,
                'Authorization' => 'Bearer '.$token,
            ])
            ->timeout(30)
            ->retry(1, 250, throw: false)
            ->asJson();

        if (app()->environment('local')) {
            $request = $request->withoutVerifying();
        }

        $response = $request->post($endpoint, $payload);

        if (! $response->successful()) {
            Log::error('Vitips shipment create failed (non-success)', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => (string) $response->body(),
                'order_id' => $payload['id_intern'] ?? null,
            ]);

            throw new RuntimeException('Vitips API error: shipment create failed.');
        }

        $json = $response->json();

        $tracking = $json['tracking_number']
            ?? $json['tracking_code']
            ?? $json['tracking']
            ?? $json['code']
            ?? $json['code_colis']
            ?? $json['code_barre']
            ?? null;

        if (blank($tracking) && is_array($json) && isset($json['data']) && is_array($json['data'])) {
            $tracking = $json['data']['tracking_number']
                ?? $json['data']['tracking_code']
                ?? $json['data']['code']
                ?? null;
        }

        if (blank($tracking) || ! is_scalar($tracking)) {
            Log::error('Vitips shipment create failed (tracking missing)', [
                'endpoint' => $endpoint,
                'order_id' => $payload['id_intern'] ?? null,
                'response' => $json,
            ]);

            throw new RuntimeException('Vitips API error: tracking number not found in response.');
        }

        return trim((string) $tracking);
    }
}
