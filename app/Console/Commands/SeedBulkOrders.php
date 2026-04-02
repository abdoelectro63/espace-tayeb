<?php

namespace App\Console\Commands;

use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ShippingCompanyCity;
use App\Services\ShippingCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedBulkOrders extends Command
{
    protected $signature = 'orders:seed-bulk
                            {--count=50 : Number of orders to create}
                            {--status=confirmed : Order status (pending, confirmed, shipped, …)}';

    protected $description = 'Create many demo orders with random customers, cities, and order lines (for testing).';

    public function handle(): int
    {
        $count = max(1, min(500, (int) $this->option('count')));
        $status = (string) $this->option('status');

        $products = Product::query()
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit(50)
            ->get();

        if ($products->isEmpty()) {
            $products = collect([
                Product::query()->create([
                    'name' => 'Produit démo bulk',
                    'slug' => 'produit-demo-bulk-'.Str::lower(Str::random(12)),
                    'description' => 'Créé automatiquement pour les commandes de test.',
                    'price' => 249.00,
                    'discount_price' => null,
                    'is_active' => true,
                    'stock' => 999,
                    'track_stock' => false,
                    'free_shipping' => false,
                ]),
            ]);
            $this->warn('Aucun produit actif : un produit démo a été créé.');
        }

        $cityPool = ShippingCompanyCity::query()
            ->active()
            ->pluck('name')
            ->map(fn (string $n): string => trim($n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($cityPool === []) {
            $cityPool = [
                'Casablanca',
                'Rabat',
                'Marrakech',
                'Fès',
                'Tanger',
                'Agadir',
                'Meknès',
                'Oujda',
            ];
        }

        $batch = Str::upper(Str::random(6));

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 1; $i <= $count; $i++) {
            $city = $cityPool[array_rand($cityPool)];
            $zone = preg_match('/casablanca/i', $city) === 1 ? 'casablanca' : 'other';

            $lineCount = random_int(1, min(2, $products->count()));
            $picked = $products->random($lineCount);
            if ($picked instanceof Product) {
                $picked = collect([$picked]);
            }

            $itemsForCalc = [];
            foreach ($picked as $product) {
                $qty = random_int(1, 3);
                $unit = (float) ($product->discount_price ?? $product->price);
                $itemsForCalc[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                ];
            }

            $fee = ShippingCalculator::feeForAdminOrderItems($itemsForCalc, $zone);
            $subtotal = OrderForm::calculateTotalFromItems($itemsForCalc);
            $total = round($subtotal + $fee, 2);

            $number = 'BULK-'.$batch.'-'.sprintf('%04d', $i);

            DB::transaction(function () use ($number, $city, $zone, $fee, $total, $status, $itemsForCalc): void {
                $order = Order::query()->create([
                    'number' => $number,
                    'customer_name' => 'Client test '.Str::random(4),
                    'customer_phone' => '06'.random_int(10_000_000, 99_999_999),
                    'shipping_address' => random_int(1, 200).' Rue test, '.$city,
                    'city' => $city,
                    'shipping_zone' => $zone,
                    'shipping_fee' => $fee,
                    'total_price' => $total,
                    'status' => $status,
                    'payment_status' => 'unpaid',
                    'notes' => 'orders:seed-bulk',
                ]);

                foreach ($itemsForCalc as $row) {
                    OrderProduct::query()->create([
                        'order_id' => $order->id,
                        'product_id' => (int) $row['product_id'],
                        'quantity' => (int) $row['quantity'],
                        'unit_price' => (float) $row['unit_price'],
                    ]);
                }
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Créé {$count} commande(s) — préfixe numéro BULK-{$batch}-…");

        return self::SUCCESS;
    }
}
