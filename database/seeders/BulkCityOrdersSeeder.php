<?php

namespace Database\Seeders;

use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Services\ShippingCalculator;
use Illuminate\Database\Seeder;

class BulkCityOrdersSeeder extends Seeder
{
    /**
     * Seed 10 demo orders across 5 Moroccan cities (2 per city), each with 1–3 random active products.
     */
    public function run(): void
    {
        $cities = [
            'Casablanca',
            'Casablanca',
            'Fès',
            'Fès',
            'El Jadida',
            'El Jadida',
            'Bouskoura',
            'Bouskoura',
            'Laâyoune',
            'Laâyoune',
        ];

        $productPool = Product::query()
            ->where('is_active', true)
            ->with('variations')
            ->get();

        $stamp = now()->format('YmdHisv');

        foreach ($cities as $index => $city) {
            $n = $index + 1;
            $zone = mb_strtolower(trim($city)) === 'casablanca' ? 'casablanca' : 'other';

            $order = Order::query()->create([
                'number' => sprintf('ET-BULK-%s-%02d', $stamp, $n),
                'customer_name' => sprintf('Client démo %d', $n),
                'customer_phone' => sprintf('06%d%07d', $n, random_int(1000000, 9999999)),
                'shipping_address' => sprintf('Adresse exemple %d, %s', $n, $city),
                'city' => $city,
                'shipping_zone' => $zone,
                'shipping_fee' => 0,
                'delivery_fee' => 0,
                'total_price' => 0,
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'notes' => 'Bulk seed — BulkCityOrdersSeeder',
            ]);

            if ($productPool->isNotEmpty()) {
                $lineCount = random_int(1, min(3, max(1, $productPool->count())));
                for ($l = 0; $l < $lineCount; $l++) {
                    /** @var Product $product */
                    $product = $productPool->random();
                    $variationId = $product->getDefaultVariation()?->id;
                    $quantity = random_int(1, 3);
                    $unitPrice = $product->finalUnitPriceForCart($variationId);

                    OrderProduct::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_variation_id' => $variationId,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                    ]);
                }

                $order->load('orderItems');
                $itemsForCalc = $order->orderItems->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->all();

                $shippingFee = ShippingCalculator::feeForAdminOrderItems($itemsForCalc, $zone);
                $subtotal = OrderForm::calculateTotalFromItems($itemsForCalc);
                $order->update([
                    'shipping_fee' => $shippingFee,
                    'total_price' => round($subtotal + $shippingFee + (float) $order->delivery_fee, 2),
                ]);
            } else {
                $order->update([
                    'shipping_fee' => $zone === 'casablanca' ? 30.00 : 45.00,
                    'total_price' => round(150.00 + ($n * 12.5), 2),
                ]);
            }
        }

        if ($productPool->isEmpty()) {
            $this->command?->warn('No active products in database: orders were created without order lines (fallback totals).');
        }
    }
}
