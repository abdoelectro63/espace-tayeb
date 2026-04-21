<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class StoreController extends Controller
{
    public function index(): View
    {
        $brandingId = Cache::remember(
            'store:index:branding-id:v2',
            now()->addMinutes(10),
            fn () => ShippingSetting::query()->value('id')
        );
        $branding = $brandingId ? ShippingSetting::query()->find($brandingId) : null;

        $categoryIds = Cache::remember(
            'store:index:category-ids:v2',
            now()->addMinutes(10),
            fn () => Category::query()->orderBy('name')->pluck('id')->all()
        );
        $categories = empty($categoryIds)
            ? collect()
            : Category::query()
                ->whereIn('id', $categoryIds)
                ->orderBy('name')
                ->get();

        $featuredProductIds = Cache::remember(
            'store:index:featured-product-ids:v2',
            now()->addMinutes(5),
            fn () => Product::query()
                ->where('is_active', true)
                ->latest()
                ->limit(8)
                ->pluck('id')
                ->all()
        );
        $featuredProducts = empty($featuredProductIds)
            ? collect()
            : Product::query()
                ->whereIn('id', $featuredProductIds)
                ->with(['category', 'upsellParents'])
                ->get()
                ->sortBy(fn (Product $product) => array_search($product->id, $featuredProductIds, true))
                ->values();

        return view('store.index', [
            'branding' => $branding,
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
        ]);
    }

    /**
     * Single catch-all for category listings and product pages.
     *
     * Two-segment URLs are ambiguous: `parent/child` can be a subcategory or a product
     * in the parent category. Subcategories are resolved first so `/electromenager/machine-cafe`
     * lists the category when it exists. When both segments are identical (`kitchenware/kitchenware`),
     * a product in the parent category with that slug wins over a same-slug subcategory so the
     * product page resolves.
     */
    public function category(string $path): View|RedirectResponse
    {
        $segments = collect(explode('/', trim($path, '/')))
            ->filter()
            ->values();

        abort_unless($segments->isNotEmpty(), 404);

        if ($segments->count() === 1) {
            $category = Category::query()
                ->where('slug', $segments[0])
                ->whereNull('category_id')
                ->firstOrFail();

            return $this->renderCategory($category);
        }

        if ($segments->count() === 2) {
            $parent = Category::query()
                ->where('slug', $segments[0])
                ->whereNull('category_id')
                ->first();

            if ($parent !== null) {
                if ($segments[0] === $segments[1]) {
                    $productInParent = Product::query()
                        ->where('slug', $segments[1])
                        ->where('category_id', $parent->id)
                        ->where('is_active', true)
                        ->first();

                    if ($productInParent !== null) {
                        return app(ProductController::class)->show((string) $segments[0], $productInParent);
                    }
                }

                $childCategory = Category::query()
                    ->where('slug', $segments[1])
                    ->where('category_id', $parent->id)
                    ->first();

                if ($childCategory !== null) {
                    return $this->renderCategory($childCategory);
                }
            }

            $product = Product::query()
                ->where('slug', $segments[1])
                ->where('is_active', true)
                ->whereHas('category', function ($q) use ($segments) {
                    $q->where('slug', $segments[0])->whereNull('category_id');
                })
                ->first();

            abort_if($product === null, 404);

            return app(ProductController::class)->show((string) $segments[0], $product);
        }

        if ($segments->count() === 3) {
            $categoryPath = $segments[0].'/'.$segments[1];

            $product = Product::query()
                ->where('slug', $segments[2])
                ->where('is_active', true)
                ->whereHas('category', function ($q) use ($segments) {
                    $q->where('slug', $segments[1])
                        ->whereHas('parent', function ($p) use ($segments) {
                            $p->where('slug', $segments[0])->whereNull('category_id');
                        });
                })
                ->first();

            abort_if($product === null, 404);

            return app(ProductController::class)->show($categoryPath, $product);
        }

        abort(404);
    }

    private function renderCategory(Category $category): View
    {
        $categoryIds = Cache::remember(
            'store:category:descendants:'.$category->id.':v1',
            now()->addMinutes(10),
            fn () => $category->selfAndDescendantCategoryIds()
        );
        $products = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->with(['category', 'upsellParents'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('store.category', [
            'category' => $category,
            'products' => $products,
        ]);
    }
}
