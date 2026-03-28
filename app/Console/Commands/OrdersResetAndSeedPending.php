<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderCarrierCitySelection;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ShippingInvoiceImport;
use App\Models\ShippingInvoiceImportLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Efface toutes les commandes (et données liées), puis crée 10 commandes « confirmed »
 * sans livreur ni société — onglet Delivery → Pending.
 */
class OrdersResetAndSeedPending extends Command
{
    protected $signature = 'orders:reset-and-seed-pending
                            {--force : Obligatoire — supprime toutes les commandes et imports facture}
                            {--count=10 : Nombre de commandes à créer}';

    protected $description = 'Supprime commandes + factures import, crée des commandes confirmed (pending livraison), sans transporteur.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Utilisez --force pour confirmer la suppression de toutes les commandes.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));

        $this->wipeOrderRelatedTables();
        $this->info('Commandes et imports facture supprimés.');

        $product = Product::query()->where('is_active', true)->first()
            ?? Product::query()->first();

        if ($product === null) {
            $this->error('Aucun produit en base : créez au moins un produit avant.');

            return self::FAILURE;
        }

        $year = now()->format('Y');

        DB::transaction(function () use ($count, $product, $year): void {
            for ($i = 1; $i <= $count; $i++) {
                $number = sprintf('ET-%s-PEND-%03d', $year, $i);

                $order = Order::query()->create([
                    'number' => $number,
                    'customer_name' => 'Client test '.$i,
                    'customer_phone' => '06123456'.str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT),
                    'shipping_address' => 'Adresse test '.$i.', Casablanca',
                    'city' => 'Casablanca',
                    'shipping_zone' => 'casablanca',
                    'shipping_fee' => 0,
                    'total_price' => 100.00,
                    'status' => 'confirmed',
                    'payment_status' => 'unpaid',
                    'paid_at' => null,
                    'delivery_man_id' => null,
                    'shipping_company' => null,
                    'shipping_company_id' => null,
                    'tracking_number' => null,
                    'shipping_provider_status' => null,
                    'notes' => null,
                ]);

                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ]);
            }
        });

        $this->info("Créées : {$count} commandes (confirmed, pending livraison).");

        return self::SUCCESS;
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
