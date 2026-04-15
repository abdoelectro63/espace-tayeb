<?php

namespace App\Models;

use App\Support\PublicDiskFileCleanup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    public const OFFER_NONE = 'none';

    public const OFFER_PERCENTAGE = 'percentage';

    public const OFFER_FREE_DELIVERY = 'free_delivery';

    /** @var list<string> */
    public const OFFER_TYPES = [
        self::OFFER_NONE,
        self::OFFER_PERCENTAGE,
        self::OFFER_FREE_DELIVERY,
    ];

    protected $fillable = [
        'name',
        'code',
        'slug',
        'description',
        'long_description',
        'specifications',
        'price',
        'discount_price',
        'images',
        'detail_images',
        'main_image',
        'image',
        'is_active',
        'category_id',
        'upsell_id',
        'stock',
        'track_stock',
        'free_shipping',
        'offer_type',
        'offer_value',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            if (blank($product->code)) {
                $product->code = self::generateUniqueCode((string) ($product->name ?? ''));
            }
        });

        static::saving(function (self $product): void {
            if ($product->upsell_id === null) {
                $product->offer_type = self::OFFER_NONE;
                $product->offer_value = null;
            }

            if ($product->offer_type !== self::OFFER_PERCENTAGE) {
                $product->offer_value = null;
            }

            if ($product->upsell_id !== null && (int) $product->upsell_id === (int) $product->id) {
                $product->upsell_id = null;
            }
        });

        static::updating(function (self $product): void {
            if ($product->isDirty('main_image')) {
                $old = $product->getOriginal('main_image');
                $new = $product->main_image;
                if (is_string($old) && $old !== '' && $old !== $new) {
                    PublicDiskFileCleanup::deletePathIfDeletable($old);
                }
            }

            if ($product->isDirty('images')) {
                $oldList = PublicDiskFileCleanup::normalizeToPathList($product->getOriginal('images'));
                $newList = PublicDiskFileCleanup::normalizeToPathList($product->images);
                foreach (array_diff($oldList, $newList) as $removed) {
                    PublicDiskFileCleanup::deletePathIfDeletable($removed);
                }
            }
        });

        static::deleting(function (self $product): void {
            PublicDiskFileCleanup::deletePathIfDeletable($product->main_image);
            foreach (PublicDiskFileCleanup::normalizeToPathList($product->images) as $path) {
                PublicDiskFileCleanup::deletePathIfDeletable($path);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * منتج مقترَح للبيع المجمّع (upsell) مع هذا المنتج.
     */
    public function upsellProduct(): BelongsTo
    {
        return $this->belongsTo(self::class, 'upsell_id');
    }

    /**
     * منتجات تشير إلى هذا المنتج كـ upsell.
     */
    public function upsellParents(): HasMany
    {
        return $this->hasMany(self::class, 'upsell_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class)->orderBy('id');
    }

    public function getDefaultVariation(): ?ProductVariation
    {
        if ($this->relationLoaded('variations')) {
            $def = $this->variations->firstWhere('is_default', true);

            return $def ?? $this->variations->first();
        }

        return $this->variations()->where('is_default', true)->first()
            ?? $this->variations()->first();
    }

    /**
     * سعر الوحدة قبل العروض: سعر المتغير أو سعر المنتج.
     */
    public function basePriceForLine(?int $variationId): float
    {
        if ($this->variations()->exists()) {
            if ($variationId !== null) {
                $v = $this->relationLoaded('variations')
                    ? $this->variations->firstWhere('id', $variationId)
                    : $this->variations()->find($variationId);
                if ($v !== null) {
                    return (float) $v->price;
                }
            }

            return (float) ($this->getDefaultVariation()?->price ?? $this->effectivePrice());
        }

        return $this->effectivePrice();
    }

    /**
     * سعر السطر في السلة مع احتساب عروض النسبة / upsell.
     */
    public function finalUnitPriceForCart(?int $variationId): float
    {
        return round($this->applyOffersToBase($this->basePriceForLine($variationId)), 2);
    }

    /**
     * تطبيق عروض النسبة على قاعدة سعر معطاة.
     */
    public function applyOffersToBase(float $base): float
    {
        // منتج أساسي له upsell: حقول العرض تخص المنتج الثاني، لا هذا السطر
        if ($this->upsell_id !== null) {
            return round((float) $base, 2);
        }

        $parent = $this->bundleOfferSource();
        if ($parent !== null) {
            if ($parent->offer_type === self::OFFER_PERCENTAGE && filled($parent->offer_value)) {
                $pct = min(100.0, max(0.0, (float) $parent->offer_value));

                return round($base * (1 - $pct / 100), 2);
            }

            return round((float) $base, 2);
        }

        if ($this->offer_type === self::OFFER_PERCENTAGE && filled($this->offer_value)) {
            $pct = min(100.0, max(0.0, (float) $this->offer_value));

            return round($base * (1 - $pct / 100), 2);
        }

        return round((float) $base, 2);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeWithOffers(Builder $query): Builder
    {
        return $query->where('offer_type', '!=', self::OFFER_NONE);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeUpsellEnabled(Builder $query): Builder
    {
        return $query->whereNotNull('upsell_id');
    }

    /**
     * Product category id plus all ancestor ids (parent chain).
     *
     * @return list<int>
     */
    public function allRelatedCategories(): array
    {
        if (! filled($this->category_id)) {
            return [];
        }

        $ids = [];
        $seen = [];
        $current = $this->category;

        if ($current === null && filled($this->category_id)) {
            $current = Category::query()->find($this->category_id);
        }

        while ($current !== null) {
            $id = (int) $current->id;
            if ($id < 1 || isset($seen[$id])) {
                break;
            }

            $ids[] = $id;
            $seen[$id] = true;
            $current = $current->parent;
        }

        return $ids;
    }

    protected $casts = [
        'images' => 'array', // سيقوم لارافل بتحويل JSON إلى مصفوفة تلقائياً
        'detail_images' => 'array',
        'specifications' => 'array',
        'free_shipping' => 'boolean',
        'track_stock' => 'boolean',
        'offer_value' => 'decimal:2',
    ];

    /**
     * المنتج الأساسي الذي يعرّف عرض النسبة/التوصيل للمنتج المقترَح (عندما يكون هذا المنتج هو الـ upsell).
     */
    public function bundleOfferSource(): ?Product
    {
        if ($this->relationLoaded('upsellParents')) {
            return $this->upsellParents->first();
        }

        return $this->upsellParents()->first();
    }

    /**
     * السعر المعروض (صفحة المنتج / بطاقة) بعد العروض.
     */
    public function getFinalPriceAttribute(): float
    {
        return round($this->applyOffersToBase($this->basePriceForLine(null)), 2);
    }

    /**
     * عرض «توصيل مجاني» من نوع العرض: للمنتج الثاني فقط عند تعريفه على المنتج الأساسي.
     */
    public function hasOfferFreeDelivery(): bool
    {
        if ($this->upsell_id !== null) {
            return false;
        }

        $parent = $this->bundleOfferSource();
        if ($parent !== null) {
            return $parent->offer_type === self::OFFER_FREE_DELIVERY;
        }

        return $this->offer_type === self::OFFER_FREE_DELIVERY;
    }

    /**
     * توصيل مجاني: العلامة free_shipping أو عرض التوصيل (على المنتج الثاني عند الاقتضاء).
     */
    public function qualifiesForFreeShipping(): bool
    {
        return (bool) $this->free_shipping || $this->hasOfferFreeDelivery();
    }

    public function hasActivePercentageOffer(): bool
    {
        if ($this->upsell_id !== null) {
            return false;
        }

        $parent = $this->bundleOfferSource();
        if ($parent !== null) {
            return $parent->offer_type === self::OFFER_PERCENTAGE
                && filled($parent->offer_value)
                && (float) $parent->offer_value > 0;
        }

        return $this->offer_type === self::OFFER_PERCENTAGE
            && filled($this->offer_value)
            && (float) $this->offer_value > 0;
    }

    /**
     * نسبة خصم للعرض على صفحة المنتج الأساسي (تخص المنتج المقترَح فقط).
     */
    public function bundleUpsellPercentageForDisplay(): ?float
    {
        if ($this->upsell_id === null) {
            return null;
        }

        if ($this->offer_type !== self::OFFER_PERCENTAGE || ! filled($this->offer_value)) {
            return null;
        }

        return (float) $this->offer_value;
    }

    /**
     * نسبة الخصم للعرض في الواجهة (من المنتج الأساسي عندما يكون هذا المنتج هو upsell).
     */
    public function displayOfferPercentage(): ?float
    {
        if (! $this->hasActivePercentageOffer()) {
            return null;
        }

        $parent = $this->bundleOfferSource();
        if ($parent !== null) {
            return (float) $parent->offer_value;
        }

        return filled($this->offer_value) ? (float) $this->offer_value : null;
    }

    /**
     * URL for files stored on the public disk (Filament uploads) or absolute URLs.
     */
    public static function publicAssetUrl(?string $path): string
    {
        if (blank($path)) {
            return asset('images/placeholder-product.svg');
        }

        $path = trim((string) $path);

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        // Legacy: Filament used default disk `local` (storage/app/private), not public.
        if (Storage::disk('local')->exists($path)) {
            return route('catalog.media', ['path' => $path]);
        }

        return asset('images/placeholder-product.svg');
    }

    public function mainImageUrl(): string
    {
        return self::publicAssetUrl($this->main_image);
    }

    /**
     * @return list<string>
     */
    public function galleryImageUrls(): array
    {
        if (! is_array($this->images)) {
            return [];
        }

        return collect($this->images)
            ->filter()
            ->map(fn ($path) => self::publicAssetUrl((string) $path))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function detailImageUrls(): array
    {
        if (! is_array($this->detail_images)) {
            return [];
        }

        return collect($this->detail_images)
            ->filter()
            ->map(fn ($path) => self::publicAssetUrl((string) $path))
            ->values()
            ->all();
    }

    public function effectivePrice(): float
    {
        return (float) (filled($this->discount_price) ? $this->discount_price : $this->price);
    }

    public function seoTitle(): string
    {
        return "{$this->name} - Espace Tayeb | Meilleur Prix au Maroc";
    }

    public function seoDescription(): string
    {
        return Str::limit(strip_tags((string) ($this->description ?? '')), 160);
    }

    /**
     * Route parameters for storefront URL (same as {@see StoreController::category()}):
     * `/{categoryPath}/{product-slug}` with path segments joined as a single `path` param.
     *
     * @return array{path: string}
     */
    public function seoRouteParams(): array
    {
        $this->loadMissing('category.parent');
        $base = $this->category?->storePath() ?? 'products';

        return [
            'path' => $base.'/'.$this->slug,
        ];
    }

    public function isOnSale(): bool
    {
        if ($this->hasActivePercentageOffer()) {
            return true;
        }

        if (! filled($this->discount_price)) {
            return false;
        }

        return (float) $this->discount_price < (float) $this->price;
    }

    public function inStock(): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        return ($this->stock ?? 0) > 0;
    }

    /**
     * Max units per order for cart UI (999 when stock is not tracked).
     */
    public function maxOrderableQuantity(): int
    {
        if (! $this->track_stock) {
            return 999;
        }

        return max(0, (int) ($this->stock ?? 0));
    }

    public static function generateUniqueCode(string $name): string
    {
        $normalized = Str::lower(Str::ascii($name));
        $lettersOnly = preg_replace('/[^a-z0-9]/', '', $normalized) ?: '';
        $prefix = substr($lettersOnly, 0, 3);

        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'x');
        }

        do {
            $candidate = $prefix.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (self::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
