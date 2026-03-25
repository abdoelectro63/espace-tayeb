<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ShippingSetting;
use Illuminate\Support\Collection;

class ShippingCalculator
{
    /**
     * @param  Collection<int, array{product: Product, quantity: int, line_total: float}>  $lines
     * @return array{
     *     requires_paid_shipping: bool,
     *     shipping_fee: float|null,
     *     subtotal: float,
     *     grand_total: float|null,
     *     zone_selected: bool,
     *     shipping_zone: string|null
     * }
     */
    public static function breakdown(
        ShoppingCart $cart,
        ?string $shippingZone,
        ?ShippingSetting $settings = null
    ): array {
        $settings ??= ShippingSetting::query()->first();
        $lines = $cart->lines();
        $subtotal = (float) $cart->subtotal();

        $requiresPaid = $lines->contains(function (array $line): bool {
            return ! $line['product']->free_shipping;
        });

        if (! $requiresPaid) {
            return [
                'requires_paid_shipping' => false,
                'shipping_fee' => 0.0,
                'subtotal' => $subtotal,
                'grand_total' => round($subtotal, 2),
                'zone_selected' => true,
                'shipping_zone' => $shippingZone,
            ];
        }

        if ($settings === null) {
            return [
                'requires_paid_shipping' => true,
                'shipping_fee' => null,
                'subtotal' => $subtotal,
                'grand_total' => null,
                'zone_selected' => false,
                'shipping_zone' => $shippingZone,
            ];
        }

        if ($shippingZone !== 'casablanca' && $shippingZone !== 'other') {
            return [
                'requires_paid_shipping' => true,
                'shipping_fee' => null,
                'subtotal' => $subtotal,
                'grand_total' => null,
                'zone_selected' => false,
                'shipping_zone' => $shippingZone,
            ];
        }

        $fee = $shippingZone === 'casablanca'
            ? (float) $settings->casablanca_fee
            : (float) $settings->other_cities_fee;

        return [
            'requires_paid_shipping' => true,
            'shipping_fee' => round($fee, 2),
            'subtotal' => $subtotal,
            'grand_total' => round($subtotal + $fee, 2),
            'zone_selected' => true,
            'shipping_zone' => $shippingZone,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $orderItems  Repeater state: product_id, quantity, unit_price
     */
    public static function feeForAdminOrderItems(?array $orderItems, string $shippingZone): float
    {
        $settings = ShippingSetting::query()->first();
        if ($settings === null) {
            return 0.0;
        }

        if ($orderItems === null || $orderItems === []) {
            return 0.0;
        }

        $productIds = collect($orderItems)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return 0.0;
        }

        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $requiresPaid = false;
        foreach ($orderItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $pid = (int) ($item['product_id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $product = $products->get($pid);
            if ($product !== null && ! $product->free_shipping) {
                $requiresPaid = true;
                break;
            }
        }

        if (! $requiresPaid) {
            return 0.0;
        }

        if ($shippingZone !== 'casablanca' && $shippingZone !== 'other') {
            return 0.0;
        }

        return $shippingZone === 'casablanca'
            ? (float) $settings->casablanca_fee
            : (float) $settings->other_cities_fee;
    }
}
