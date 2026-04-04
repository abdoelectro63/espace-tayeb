<?php

namespace App\Models;

use App\Support\PublicDiskFileCleanup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'category_id', 'image', 'icon'];

    protected static function booted(): void
    {
        static::updating(function (self $category): void {
            if ($category->isDirty('image')) {
                $old = $category->getOriginal('image');
                $new = $category->image;
                if (is_string($old) && $old !== '' && $old !== $new) {
                    PublicDiskFileCleanup::deletePathIfDeletable($old);
                }
            }
        });

        static::deleting(function (self $category): void {
            PublicDiskFileCleanup::deletePathIfDeletable($category->image);
        });
    }

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

    /**
     * This category's id plus every descendant category id (any depth).
     * Use with Product queries: `whereIn('category_id', $category->selfAndDescendantCategoryIds())`.
     *
     * Implemented with a single recursive CTE on supported drivers to avoid N+1 queries
     * and to avoid loading the full category tree into memory.
     *
     * @return list<int>
     */
    public function selfAndDescendantCategoryIds(): array
    {
        $id = (int) $this->getKey();
        if ($id < 1) {
            throw new InvalidArgumentException('Category must be persisted with a valid id.');
        }

        $connection = $this->getConnection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlite'], true)) {
            $grammar = $connection->getQueryGrammar();
            $table = $grammar->wrapTable($this->getTable());
            $idCol = $grammar->wrap('id');
            $parentCol = $grammar->wrap('category_id');

            $sql = "
                WITH RECURSIVE subtree AS (
                    SELECT {$idCol} FROM {$table} WHERE {$idCol} = ?
                    UNION ALL
                    SELECT c.{$idCol} FROM {$table} AS c
                    INNER JOIN subtree AS s ON c.{$parentCol} = s.{$idCol}
                )
                SELECT {$idCol} AS id FROM subtree
            ";

            $rows = $connection->select($sql, [$id]);

            return array_values(array_map(fn ($row) => (int) $row->id, $rows));
        }

        return $this->selfAndDescendantCategoryIdsBreadthFirst($id);
    }

    /**
     * One query per tree level (bounded by depth, not product count).
     *
     * @return list<int>
     */
    protected function selfAndDescendantCategoryIdsBreadthFirst(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $next = self::query()
                ->whereIn('category_id', $frontier)
                ->pluck('id')
                ->all();

            if ($next === []) {
                break;
            }

            foreach ($next as $childId) {
                $ids[] = (int) $childId;
            }

            $frontier = $next;
        }

        return $ids;
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
