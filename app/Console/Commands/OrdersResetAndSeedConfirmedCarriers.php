<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderCarrierCitySelection;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\ShippingInvoiceImport;
use App\Models\ShippingInvoiceImportLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Efface commandes + imports facture, recrée des commandes « confirmed » Vitips + Express Coursier
 * avec trackings préremplis — sans appel API (pas de sync).
 */
class OrdersResetAndSeedConfirmedCarriers extends Command
{
    /** @var list<string> */
    private const VITIPS_TRACKINGS = [
        'CL-9082631457', 'CL-5274983061', 'CL-6731295084', 'CL-1209758643', 'CL-1487629530',
        'CL-8459612370', 'CL-1496587032', 'CL-7489051326', 'CL-3245710698', 'CL-9635872041',
        'CL-1394508276', 'CL-7153849062', 'CL-9587406231', 'CL-2680419375', 'CL-1653089247',
        'CL-0496287351', 'CL-6512047389', 'CL-9801534627', 'CL-0714965328', 'CL-5603417982',
        'CL-3187690254', 'CL-9572086134', 'CL-8013579426', 'CL-9457603821', 'CL-6752904318',
        'CL-0764183592', 'CL-8164932075', 'CL-3509748162', 'CL-7169804253', 'CL-8746925130',
        'CL-7912840365', 'CL-5236849701', 'CL-4037291685', 'CL-2186304795', 'CL-5391680247',
        'CL-9402386715', 'CL-2641075938', 'CL-2718059364', 'CL-6987413250', 'CL-2145960387',
        'CL-1635987420', 'CL-4095871623', 'CL-2518936740',
    ];

    /** @var list<string> */
    private const EXPRESS_RAW_IDS = [
        '2603161519-5530187286191', '2603161519-553C2833019535', '2603161519-553C543720953',
        '2603161519-553C4294625868', '2603161519-553C2375909392', '2603161519-553C161814636',
        '2603161519-553C2327539547', '2603161519-553C2532378652', '2603161519-553C2559297441',
        '2603161519-55303427787140', '2603161519-553C4112218638', '2603131458-553C1968500315',
        '2603131452-553C1270342892', '2603121409-553C2727589548', '2603121409-55303277553815',
        '2603111457-553C918850525', '2603111457-553C2135074017', '2603091526-553C1629354121',
    ];

    /** @var list<string> */
    private const CITIES = [
        'Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda',
        'Kenitra', 'Tétouan', 'Safi', 'Mohammedia', 'El Jadida', 'Beni Mellal', 'Nador',
    ];

    protected $signature = 'orders:reset-and-seed-confirmed-carriers
                            {--force : Obligatoire — supprime toutes les commandes et imports facture}
                            {--vitips-company=Vitips : Nom société Vitips}
                            {--express-company=Express Coursier : Nom société Express Coursier}';

    protected $description = 'Supprime commandes + factures import, crée commandes confirmed (Vitips + Express) avec tracking, sans API.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Utilisez --force pour confirmer.');

            return self::FAILURE;
        }

        $this->wipeOrderRelatedTables();
        $this->info('Imports facture (/admin/shipping-invoice-imports) et commandes supprimés.');

        $vitipsName = (string) $this->option('vitips-company');
        $expressName = (string) $this->option('express-company');

        ShippingCompany::query()->firstOrCreate(
            ['name' => $vitipsName],
            ['color' => 'warning'],
        );
        ShippingCompany::query()->firstOrCreate(
            ['name' => $expressName],
            ['color' => 'info'],
        );

        $product = Product::query()->where('is_active', true)->first()
            ?? Product::query()->first();

        if ($product === null) {
            $this->error('Aucun produit : créez un produit avant.');

            return self::FAILURE;
        }

        $created = 0;

        DB::transaction(function () use ($vitipsName, $expressName, $product, &$created): void {
            foreach (self::VITIPS_TRACKINGS as $i => $tracking) {
                $city = self::CITIES[$i % count(self::CITIES)];
                $phone = '06'.str_pad((string) (($i * 791 + 1234567) % 100000000), 8, '0', STR_PAD_LEFT);
                $number = $this->uniqueOrderNumber('VIT', $tracking);

                $order = Order::query()->create([
                    'number' => $number,
                    'customer_name' => 'Client Vitips '.$tracking,
                    'customer_phone' => $phone,
                    'shipping_address' => 'Adresse, '.$city,
                    'city' => $city,
                    'shipping_zone' => 'other',
                    'shipping_fee' => 0,
                    'total_price' => 120.00,
                    'status' => 'confirmed',
                    'payment_status' => 'unpaid',
                    'paid_at' => null,
                    'delivery_man_id' => null,
                    'shipping_company' => null,
                    'shipping_company_id' => null,
                    'tracking_number' => null,
                    'shipping_provider_status' => null,
                    'notes' => 'demo pending — prévu '.$vitipsName.' — '.$tracking,
                ]);

                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 120.00,
                ]);
                $created++;
            }

            foreach (self::EXPRESS_RAW_IDS as $i => $rawId) {
                $tracking = 'CL-'.$rawId;
                $city = self::CITIES[$i % count(self::CITIES)];
                $phone = '06'.str_pad((string) (($i * 617 + 2234567) % 100000000), 8, '0', STR_PAD_LEFT);
                $number = $this->uniqueOrderNumber('EXP', $tracking);

                $order = Order::query()->create([
                    'number' => $number,
                    'customer_name' => 'Client Express '.$rawId,
                    'customer_phone' => $phone,
                    'shipping_address' => 'Adresse, '.$city,
                    'city' => $city,
                    'shipping_zone' => 'other',
                    'shipping_fee' => 0,
                    'total_price' => 120.00,
                    'status' => 'confirmed',
                    'payment_status' => 'unpaid',
                    'paid_at' => null,
                    'delivery_man_id' => null,
                    'shipping_company' => null,
                    'shipping_company_id' => null,
                    'tracking_number' => null,
                    'shipping_provider_status' => null,
                    'notes' => 'demo pending — prévu '.$expressName.' — CL-'.$rawId,
                ]);

                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 120.00,
                ]);
                $created++;
            }
        });

        $this->info("Créées : {$created} commandes (confirmed, sans société de livraison) — Vitips : ".count(self::VITIPS_TRACKINGS).', Express : '.count(self::EXPRESS_RAW_IDS).'. Assignez via « Assigner a une societe de livraison » pour sync API.');

        return self::SUCCESS;
    }

    private function uniqueOrderNumber(string $prefix, string $tracking): string
    {
        $digits = preg_replace('/\D+/', '', preg_replace('/^CL-/i', '', $tracking)) ?? '';
        $base = $prefix.'-DLV-'.$digits;

        if (Order::withTrashed()->where('number', $base)->exists()) {
            return $base.'-'.Str::lower(Str::random(4));
        }

        return $base;
    }

    private function wipeOrderRelatedTables(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            if (Schema::hasTable('shipping_invoice_import_lines')) {
                ShippingInvoiceImportLine::query()->delete();
            }
            if (Schema::hasTable('shipping_invoice_imports')) {
                ShippingInvoiceImport::query()->delete();
            }
            if (Schema::hasTable('order_carrier_city_selections')) {
                OrderCarrierCitySelection::query()->delete();
            }
            if (Schema::hasTable('order_products')) {
                OrderProduct::query()->delete();
            }
            if (Schema::hasTable('order_product')) {
                DB::table('order_product')->delete();
            }
            if (Schema::hasTable('order_items')) {
                DB::table('order_items')->delete();
            }
            if (Schema::hasTable('orders')) {
                DB::table('orders')->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
