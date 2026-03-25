<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
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

    public function category()
    {
        return $this->belongsTo(Category::class);
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
}
