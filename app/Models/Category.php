<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'category_id', 'image', 'icon'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'category_id');
    }

    /**
     * Public URL for the category image, or null when none is set.
     */
    public function imageUrl(): ?string
    {
        if (blank($this->image)) {
            return null;
        }

        return Product::publicAssetUrl($this->image);
    }
}
