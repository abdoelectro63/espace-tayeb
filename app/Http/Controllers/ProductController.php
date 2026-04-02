<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    public function show(string $categoryPath, Product $product): View|RedirectResponse
    {
        abort_unless((bool) $product->is_active, 404);

        $product->loadMissing([
            'category.parent',
            'upsellProduct.category.parent',
            'upsellProduct.upsellParents',
            'upsellParents',
            'variations',
        ]);

        $expectedPath = $product->category?->storePath();
        abort_if(blank($expectedPath), 404);

        if (trim($categoryPath, '/') !== trim($expectedPath, '/')) {
            return redirect()->route('store.category', $product->seoRouteParams(), 301);
        }

        return $this->renderShowPage($product);
    }

    private function renderShowPage(Product $product): View
    {
        $product->loadMissing([
            'category.parent',
            'upsellProduct.category.parent',
            'upsellProduct.upsellParents',
            'upsellParents',
            'variations',
        ]);

        $relatedProducts = $this->relatedProductsFor($product, 8);

        return view('products.show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }

    /**
     * Prefer same category, then sibling categories (same parent), then subcategories under a root category, then any active products.
     *
     * @return Collection<int, Product>
     */
    private function relatedProductsFor(Product $product, int $limit): Collection
    {
        $with = ['category.parent', 'upsellParents'];
        $picked = collect();

        $base = Product::query()
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->with($with);

        if ($product->category_id) {
            $same = (clone $base)
                ->where('category_id', $product->category_id)
                ->latest()
                ->limit($limit)
                ->get();
            $picked = $picked->merge($same);
        }

        if ($picked->count() < $limit && $product->category) {
            $extraIds = $this->extendedCategoryIdsForRelated($product->category);
            if ($extraIds !== []) {
                $exclude = $picked->pluck('id')->push($product->id)->all();
                $need = $limit - $picked->count();
                $more = Product::query()
                    ->where('id', '!=', $product->id)
                    ->where('is_active', true)
                    ->whereIn('category_id', $extraIds)
                    ->whereNotIn('id', $exclude)
                    ->with($with)
                    ->inRandomOrder()
                    ->limit($need)
                    ->get();
                $picked = $picked->merge($more);
            }
        }

        if ($picked->count() < $limit) {
            $exclude = $picked->pluck('id')->push($product->id)->all();
            $need = $limit - $picked->count();
            $fill = Product::query()
                ->where('is_active', true)
                ->whereNotIn('id', $exclude)
                ->with($with)
                ->inRandomOrder()
                ->limit($need)
                ->get();
            $picked = $picked->merge($fill);
        }

        return $picked->unique('id')->take($limit)->values();
    }

    /**
     * @return list<int>
     */
    private function extendedCategoryIdsForRelated(Category $category): array
    {
        if ($category->category_id !== null) {
            return Category::query()
                ->where('category_id', $category->category_id)
                ->where('id', '!=', $category->id)
                ->pluck('id')
                ->all();
        }

        return Category::query()
            ->where('category_id', $category->id)
            ->pluck('id')
            ->all();
    }
}
