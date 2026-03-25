<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class ShoppingCart
{
    private const string SESSION_KEY = 'shopping_cart';

    /**
     * @return array<int, int> product_id => quantity
     */
    public function items(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    /**
     * @param  array<int, int>  $items
     */
    public function replace(array $items): void
    {
        Session::put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function quantity(int $productId): int
    {
        return (int) ($this->items()[$productId] ?? 0);
    }

    public function totalQuantity(): int
    {
        return (int) array_sum($this->items());
    }

    public function lineCount(): int
    {
        return count($this->items());
    }

    public function add(Product $product, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $items = $this->items();
        $productId = $product->id;
        $current = $items[$productId] ?? 0;
        $items[$productId] = $current + $quantity;
        $items[$productId] = $this->capQuantityForStock($product, $items[$productId]);
        if ($items[$productId] < 1) {
            unset($items[$productId]);
        }
        $this->replace($items);
    }

    public function setQuantity(Product $product, int $quantity): void
    {
        $items = $this->items();
        $productId = $product->id;

        if ($quantity < 1) {
            unset($items[$productId]);
            $this->replace($items);

            return;
        }

        $items[$productId] = $this->capQuantityForStock($product, $quantity);
        if ($items[$productId] < 1) {
            unset($items[$productId]);
        }
        $this->replace($items);
    }

    public function remove(int $productId): void
    {
        $items = $this->items();
        unset($items[$productId]);
        $this->replace($items);
    }

    /**
     * Drop inactive or missing products and cap quantities to current stock.
     */
    public function syncWithCatalog(): void
    {
        $items = $this->items();
        if ($items === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', array_keys($items))
            ->get()
            ->keyBy('id');

        $next = [];
        foreach ($items as $productId => $quantity) {
            $product = $products->get($productId);
            if ($product === null || ! $product->is_active) {
                continue;
            }
            $capped = $this->capQuantityForStock($product, (int) $quantity);
            if ($capped > 0) {
                $next[$productId] = $capped;
            }
        }

        $this->replace($next);
    }

    /**
     * @return Collection<int, array{product: Product, quantity: int, line_total: float}>
     */
    public function lines(): Collection
    {
        $this->syncWithCatalog();

        $items = $this->items();
        if ($items === []) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', array_keys($items))
            ->where('is_active', true)
            ->with('category')
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (int $quantity, int $productId) use ($products) {
                $product = $products->get($productId);
                if ($product === null) {
                    return null;
                }

                $quantity = $this->capQuantityForStock($product, $quantity);
                if ($quantity < 1) {
                    return null;
                }
                $lineTotal = round($product->effectivePrice() * $quantity, 2);

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            })
            ->filter()
            ->values();
    }

    public function subtotal(): float
    {
        return round(
            (float) $this->lines()->sum('line_total'),
            2
        );
    }

    private function capQuantityForStock(Product $product, int $quantity): int
    {
        if (! $product->track_stock) {
            return min($quantity, 999);
        }

        $stock = (int) ($product->stock ?? 0);
        if ($stock < 1) {
            return 0;
        }

        return min($quantity, $stock);
    }
}
