<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'slug',
        'description',
        'price',
        'discount_price',
        'images',
        'main_image',
        'is_active',
        'category_id',
        'stock',
        'track_stock',
        'free_shipping',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            if (blank($product->code)) {
                $product->code = self::generateUniqueCode((string) ($product->name ?? ''));
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
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
        'free_shipping' => 'boolean',
        'track_stock' => 'boolean',
    ];

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
     * Route parameters for SEO product URL:
     * /{parent?}/{category}/{product-slug}
     *
     * @return array{categoryPath:string,product:string}
     */
    public function seoRouteParams(): array
    {
        $this->loadMissing('category.parent');

        return [
            'categoryPath' => $this->category?->storePath() ?? 'products',
            'product' => $this->slug,
        ];
    }

    public function isOnSale(): bool
    {
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
