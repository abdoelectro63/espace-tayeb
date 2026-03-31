<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class ShoppingCart
{
    private const string SESSION_KEY = 'shopping_cart_v2';

    /**
     * Legacy session used product_id => quantity.
     *
     * @return array<string, int> line_key => quantity (line_key = "productId|variationIdOr0")
     */
    public function items(): array
    {
        $raw = Session::get(self::SESSION_KEY);
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $firstKey = (string) array_key_first($raw);
        if (str_contains($firstKey, '|')) {
            /** @var array<string, int> */
            return array_map(fn (int|string $q): int => (int) $q, $raw);
        }

        $migrated = [];
        foreach ($raw as $productId => $qty) {
            if (! is_numeric($productId)) {
                continue;
            }
            $migrated[$this->lineKey((int) $productId, null)] = (int) $qty;
        }
        if ($migrated !== []) {
            Session::put(self::SESSION_KEY, $migrated);
        }

        return $migrated;
    }

    /**
     * @param  array<string, int>  $items
     */
    public function replace(array $items): void
    {
        Session::put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function lineKey(int $productId, ?int $variationId): string
    {
        return $productId.'|'.($variationId ?? 0);
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    public function parseLineKey(string $key): array
    {
        $parts = explode('|', $key, 2);
        $pid = (int) ($parts[0] ?? 0);
        $vid = isset($parts[1]) ? (int) $parts[1] : 0;

        return [$pid, $vid === 0 ? null : $vid];
    }

    public function quantity(int $productId, ?int $productVariationId = null): int
    {
        return (int) ($this->items()[$this->lineKey($productId, $productVariationId)] ?? 0);
    }

    public function totalQuantity(): int
    {
        return (int) array_sum($this->items());
    }

    public function lineCount(): int
    {
        return count($this->items());
    }

    public function add(Product $product, int $quantity, ?int $productVariationId = null): void
    {
        if ($quantity < 1) {
            return;
        }

        if ($product->variations()->exists()) {
            if ($productVariationId === null) {
                $productVariationId = $product->getDefaultVariation()?->id;
            }
        } else {
            $productVariationId = null;
        }

        $items = $this->items();
        $key = $this->lineKey($product->id, $productVariationId);
        $current = $items[$key] ?? 0;
        $items[$key] = $current + $quantity;
        $items[$key] = $this->capQuantityForStock($product, $items[$key]);
        if ($items[$key] < 1) {
            unset($items[$key]);
        }
        $this->replace($items);
    }

    public function setQuantity(Product $product, int $quantity, ?int $productVariationId = null): void
    {
        if ($product->variations()->exists() && $productVariationId === null) {
            $productVariationId = $product->getDefaultVariation()?->id;
        }
        if (! $product->variations()->exists()) {
            $productVariationId = null;
        }

        $items = $this->items();
        $key = $this->lineKey($product->id, $productVariationId);

        if ($quantity < 1) {
            unset($items[$key]);
            $this->replace($items);

            return;
        }

        $items[$key] = $this->capQuantityForStock($product, $quantity);
        if ($items[$key] < 1) {
            unset($items[$key]);
        }
        $this->replace($items);
    }

    public function remove(int $productId, ?int $productVariationId = null): void
    {
        $items = $this->items();
        unset($items[$this->lineKey($productId, $productVariationId)]);
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

        $productIds = collect($items)
            ->map(fn (int $qty, string $key): int => $this->parseLineKey($key)[0])
            ->unique()
            ->values()
            ->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with(['upsellParents', 'variations'])
            ->get()
            ->keyBy('id');

        $next = [];
        foreach ($items as $key => $quantity) {
            [$productId, $variationId] = $this->parseLineKey($key);
            $product = $products->get($productId);
            if ($product === null || ! $product->is_active) {
                continue;
            }
            if ($variationId !== null) {
                $v = $product->variations->firstWhere('id', $variationId);
                if ($v === null) {
                    continue;
                }
            } elseif ($product->variations->isNotEmpty()) {
                continue;
            }
            $capped = $this->capQuantityForStock($product, (int) $quantity);
            if ($capped > 0) {
                $next[$key] = $capped;
            }
        }

        $this->replace($next);
    }

    /**
     * @return Collection<int, array{
     *     product: Product,
     *     product_variation: ProductVariation|null,
     *     quantity: int,
     *     line_total: float
     * }>
     */
    public function lines(): Collection
    {
        $this->syncWithCatalog();

        $items = $this->items();
        if ($items === []) {
            return collect();
        }

        $productIds = collect($items)
            ->map(fn (int $qty, string $key): int => $this->parseLineKey($key)[0])
            ->unique()
            ->values()
            ->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->with(['category', 'upsellParents', 'variations'])
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (int $quantity, string $key) use ($products) {
                [$productId, $variationId] = $this->parseLineKey($key);
                $product = $products->get($productId);
                if ($product === null) {
                    return null;
                }

                $variation = null;
                if ($variationId !== null) {
                    $variation = $product->variations->firstWhere('id', $variationId);
                    if ($variation === null) {
                        return null;
                    }
                } elseif ($product->variations->isNotEmpty()) {
                    return null;
                }

                $quantity = $this->capQuantityForStock($product, $quantity);
                if ($quantity < 1) {
                    return null;
                }

                $unit = $product->finalUnitPriceForCart($variation?->id);
                $lineTotal = round($unit * $quantity, 2);

                return [
                    'product' => $product,
                    'product_variation' => $variation,
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
