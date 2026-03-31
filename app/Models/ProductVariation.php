<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    protected static function booted(): void
    {
        static::saved(function (self $variation): void {
            if ($variation->is_default) {
                self::query()
                    ->where('product_id', $variation->product_id)
                    ->whereKeyNot($variation->getKey())
                    ->update(['is_default' => false]);
            }
        });
    }

    protected $fillable = [
        'product_id',
        'name',
        'value',
        'sku',
        'price',
        'is_default',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function label(): string
    {
        return trim($this->name.': '.$this->value);
    }
}
