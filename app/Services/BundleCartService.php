<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * إضافة منتج + upsell في طلب واحد مع قفل مخزون وتحقق من العلاقة.
 */
class BundleCartService
{
    public function __construct(
        private readonly ShoppingCart $cart,
    ) {}

    /**
     * @throws RuntimeException
     */
    public function addBundle(int $primaryProductId, int $upsellProductId, int $quantity = 1): void
    {
        if ($quantity < 1 || $quantity > 999) {
            throw new RuntimeException('الكمية غير صالحة.');
        }

        DB::transaction(function () use ($primaryProductId, $upsellProductId, $quantity): void {
            /** @var Product $primary */
            $primary = Product::query()
                ->with('variations')
                ->lockForUpdate()
                ->whereKey($primaryProductId)
                ->where('is_active', true)
                ->firstOrFail();

            /** @var Product $upsell */
            $upsell = Product::query()
                ->with('variations')
                ->lockForUpdate()
                ->whereKey($upsellProductId)
                ->where('is_active', true)
                ->firstOrFail();

            if ((int) $primary->upsell_id !== (int) $upsell->id) {
                throw new RuntimeException('المنتجان لا يشكلان عرضاً مجمّعاً صالحاً.');
            }

            if ($primary->id === $upsell->id) {
                throw new RuntimeException('لا يمكن إضافة نفس المنتج مرتين.');
            }

            if (! $primary->inStock() || ! $upsell->inStock()) {
                throw new RuntimeException('أحد المنتجين غير متوفر.');
            }

            $primaryVid = $primary->getDefaultVariation()?->id;
            $upsellVid = $upsell->getDefaultVariation()?->id;
            $currentPrimary = $this->cart->quantity($primary->id, $primaryVid);
            $currentUpsell = $this->cart->quantity($upsell->id, $upsellVid);

            if ($primary->track_stock) {
                $max = (int) $primary->stock;
                if ($currentPrimary + $quantity > $max) {
                    throw new RuntimeException('الكمية تتجاوز مخزون المنتج الأساسي.');
                }
            }

            if ($upsell->track_stock) {
                $max = (int) $upsell->stock;
                if ($currentUpsell + $quantity > $max) {
                    throw new RuntimeException('الكمية تتجاوز مخزون المنتج المقترَح.');
                }
            }

            $this->cart->add($primary, $quantity, $primaryVid);
            $this->cart->add($upsell, $quantity, $upsellVid);
        });
    }
}
