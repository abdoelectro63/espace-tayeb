<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function scopeOnlyParents(Builder $query): Builder
    {
        return $query->whereNull('category_id');
    }

    public function storePath(): string
    {
        if ($this->parent?->slug) {
            return $this->parent->slug.'/'.$this->slug;
        }

        return $this->slug;
    }

    public function seoTitle(): string
    {
        return "{$this->name} - Espace Tayeb | Meilleur Prix au Maroc";
    }

    public function seoDescription(): string
    {
        return Str::limit("Découvrez les meilleurs produits de {$this->name} chez Espace Tayeb au Maroc.", 160);
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
