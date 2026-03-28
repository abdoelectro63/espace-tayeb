<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Services\Shipping\ShippingManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SeedExpressCoursierInvoiceTestOrders extends Command
{
    /**
     * Identifiants colis Express (tels qu’affichés côté transporteur), stockés en base avec le préfixe CL-.
     *
     * @var list<string>
     */
    private const RAW_TRACKING_IDS = [
        '2603161519-5530187286191',
        '2603161519-553C2833019535',
        '2603161519-553C543720953',
        '2603161519-553C4294625868',
        '2603161519-553C2375909392',
        '2603161519-553C161814636',
        '2603161519-553C2327539547',
        '2603161519-553C2532378652',
        '2603161519-553C2559297441',
        '2603161519-55303427787140',
        '2603161519-553C4112218638',
        '2603131458-553C1968500315',
        '2603131452-553C1270342892',
        '2603121409-553C2727589548',
        '2603121409-55303277553815',
        '2603111457-553C918850525',
        '2603111457-553C2135074017',
        '2603091526-553C1629354121',
    ];

    /** @var list<string> */
    private const CITIES = [
        'Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda',
        'Kenitra', 'Tétouan', 'Safi', 'Mohammedia', 'El Jadida', 'Beni Mellal', 'Nador',
    ];

    protected $signature = 'orders:seed-express-coursier-test
                            {--fresh : Supprimer les commandes seed Express puis recréer}
                            {--company=Express Coursier : Nom de la société (doit contenir « Express Coursier »)}
                            {--sync : Après création, envoyer les colis à l’API Express Coursier (token + store_id)}';

    protected $description = 'Crée des commandes Express Coursier avec tracking_number = CL-{id}, statut shipped + unpaid, pour tests / import facture.';

    public function handle(ShippingManager $shippingManager): int
    {
        $companyName = (string) $this->option('company');

        $company = ShippingCompany::query()->firstOrCreate(
            ['name' => $companyName],
            ['color' => 'info'],
        );

        $allTrackings = array_map(fn (string $raw): string => 'CL-'.$raw, self::RAW_TRACKING_IDS);

        if ($this->option('fresh')) {
            Order::withTrashed()
                ->where(function ($q) use ($allTrackings): void {
                    $q->whereIn('notes', ['express-coursier-seed', 'express-invoice-test'])
                        ->orWhereIn('tracking_number', $allTrackings);
                })
                ->forceDelete();
            $this->info('Anciennes commandes express-coursier-seed (ou mêmes trackings) supprimées définitivement.');
        }

        $product = Product::query()->where('is_active', true)->first()
            ?? Product::query()->first();

        if ($product === null) {
            $this->error('Aucun produit en base : crée au moins un produit avant.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($company, $companyName, $product, &$created, &$updated): void {
            foreach (self::RAW_TRACKING_IDS as $i => $rawId) {
                $tracking = 'CL-'.$rawId;
                $city = self::CITIES[$i % count(self::CITIES)];
                $phone = '06'.str_pad((string) (($i * 617 + 2234567) % 100000000), 8, '0', STR_PAD_LEFT);
                $number = $this->uniqueOrderNumberForTracking($tracking);

                $existing = Order::withTrashed()
                    ->where('tracking_number', $tracking)
                    ->first();

                if ($existing !== null && $existing->trashed()) {
                    $existing->restore();
                }

                $payload = [
                    'customer_name' => 'Client Express '.$rawId,
                    'customer_phone' => $phone,
                    'shipping_address' => 'Adresse seed, '.$city,
                    'city' => $city,
                    'shipping_zone' => 'other',
                    'shipping_fee' => 0,
                    'total_price' => 120.00,
                    'status' => 'shipped',
                    'payment_status' => 'unpaid',
                    'paid_at' => null,
                    'delivery_man_id' => null,
                    'shipping_company' => $companyName,
                    'shipping_company_id' => $company->id,
                    'tracking_number' => $tracking,
                    'shipping_provider_status' => 'Seed Express Coursier',
                    'notes' => 'express-coursier-seed',
                ];

                if ($existing !== null) {
                    $existing->update($payload);
                    $this->ensureOrderHasLine($existing, $product);
                    $updated++;

                    continue;
                }

                $order = Order::query()->create(array_merge($payload, [
                    'number' => $number,
                ]));

                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 120.00,
                ]);

                $created++;
            }
        });

        $this->info("Créées : {$created} | mises à jour : {$updated}. tracking_number = CL- + identifiant colis, {$companyName}, shipped + unpaid.");

        if ($this->option('sync')) {
            if (blank($company->api_token)) {
                $this->warn('Option --sync ignorée : renseignez un token API sur la société de livraison (delivery_token / token).');

                return self::SUCCESS;
            }

            $orders = Order::query()
                ->with('orderItems.product')
                ->whereIn('notes', ['express-coursier-seed'])
                ->where('shipping_company_id', $company->id)
                ->orderBy('id')
                ->get();

            if ($orders->isEmpty()) {
                $this->warn('Aucune commande à synchroniser.');

                return self::SUCCESS;
            }

            $success = 0;
            $failed = 0;

            foreach ($orders->chunk(15) as $chunk) {
                try {
                    $batch = $shippingManager->processMany($chunk, $company->id);
                    $results = $batch['results'] ?? [];
                    $batchProvider = $batch['provider'] ?? null;
                } catch (\Throwable $e) {
                    Log::error('SeedExpressCoursier: batch API failed', [
                        'shipping_company_id' => $company->id,
                        'exception' => $e->getMessage(),
                    ]);
                    $this->error('Échec API Express Coursier : '.$e->getMessage());

                    return self::FAILURE;
                }

                $batchOrders = $chunk->values();
                $batchOrderCount = $batchOrders->count();
                $isExpressBatch = $batchProvider === 'express_coursier';

                foreach ($batchOrders as $batchIndex => $order) {
                    try {
                        $result = $results[$order->id] ?? [
                            'code' => 'error',
                            'message' => 'No result returned for this order.',
                            'tracking_number' => null,
                            'response' => [],
                        ];

                        if (($result['code'] ?? '') !== 'ok') {
                            $failed++;
                            $this->warn('Order #'.$order->id.': '.trim((string) ($result['message'] ?? 'API not ok')));

                            continue;
                        }

                        $responsePayload = is_array($result['response'] ?? null) ? $result['response'] : [];
                        $batchIdx = $isExpressBatch ? $batchIndex : null;
                        $batchTot = $isExpressBatch ? $batchOrderCount : null;

                        $parsedTracking = $shippingManager->parseTrackingFromProviderResponseForOrder(
                            $responsePayload,
                            $order,
                            $batchIdx,
                            $batchTot,
                        );
                        $tracking = filled($parsedTracking)
                            ? $parsedTracking
                            : ($result['tracking_number'] ?? null);

                        $providerStatus = $shippingManager->parseProviderStatusForOrder(
                            $responsePayload,
                            $order,
                            $batchIdx,
                            $batchTot,
                        );

                        $updatePayload = [
                            'shipping_company_id' => $company->id,
                            'shipping_company' => $company->name,
                            'delivery_man_id' => null,
                            'status' => 'shipped',
                            'tracking_number' => $tracking ?? $order->tracking_number,
                        ];

                        if (filled($providerStatus)) {
                            $updatePayload['shipping_provider_status'] = $providerStatus;
                        }

                        $order->update($updatePayload);
                        $success++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('SeedExpressCoursier: order sync failed', [
                            'order_id' => $order->id,
                            'exception' => $e->getMessage(),
                        ]);
                        $this->warn('Order #'.$order->id.': '.$e->getMessage());
                    }
                }
            }

            $this->info("Sync API : OK {$success} | échecs {$failed}. Si l’API renvoie un autre code, le tracking en base est mis à jour (sinon le CL- initial est conservé).");
        }

        return self::SUCCESS;
    }

    private function uniqueOrderNumberForTracking(string $tracking): string
    {
        $suffix = preg_replace('/^CL-/i', '', $tracking);
        $base = 'EXP-DLV-'.preg_replace('/\D+/', '', $suffix);

        $conflict = Order::withTrashed()
            ->where('number', $base)
            ->where(function ($q) use ($tracking): void {
                $q->whereNull('tracking_number')
                    ->orWhere('tracking_number', '!=', $tracking);
            })
            ->exists();

        if ($conflict) {
            return $base.'-'.Str::lower(Str::random(4));
        }

        return $base;
    }

    private function ensureOrderHasLine(Order $order, Product $product): void
    {
        if ($order->orderItems()->exists()) {
            return;
        }

        OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 120.00,
        ]);
    }
}
