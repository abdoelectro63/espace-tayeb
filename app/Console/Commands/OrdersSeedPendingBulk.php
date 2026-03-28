<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crée des commandes « confirmed » sans livreur ni société (onglet Delivery → Pending),
 * sans supprimer les commandes existantes.
 */
class OrdersSeedPendingBulk extends Command
{
    protected $signature = 'orders:seed-pending-bulk
                            {--count=20 : Nombre de commandes à créer}';

    protected $description = 'Crée des commandes confirmed (pending livraison) en lot, sans effacer les données existantes.';

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));

        $product = Product::query()->where('is_active', true)->first()
            ?? Product::query()->first();

        if ($product === null) {
            $this->error('Aucun produit en base : créez au moins un produit avant.');

            return self::FAILURE;
        }

        $year = now()->format('Y');
        $created = 0;

        DB::transaction(function () use ($count, $product, $year, &$created): void {
            for ($i = 1; $i <= $count; $i++) {
                $suffix = str_replace('.', '', uniqid((string) $i, true));
                $number = sprintf('ET-%s-BULK-%s', $year, $suffix);

                $order = Order::query()->create([
                    'number' => $number,
                    'customer_name' => 'Client lot '.$i,
                    'customer_phone' => '06'.str_pad((string) (($i * 137 + 12345678) % 100000000), 8, '0', STR_PAD_LEFT),
                    'shipping_address' => 'Adresse lot '.$i.', Casablanca',
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
                $created++;
            }
        });

        $this->info("Créées : {$created} commandes (confirmed, pending livraison).");

        return self::SUCCESS;
    }
}
