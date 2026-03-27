<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingSetting;
use Illuminate\Contracts\View\View;

class StoreController extends Controller
{
    public function index(): View
    {
        $branding = ShippingSetting::query()->first();
        $categories = Category::query()
            ->orderBy('name')
            ->get();

        $featuredProducts = Product::query()
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->limit(8)
            ->get();

        return view('store.index', [
            'branding' => $branding,
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
        ]);
    }

    public function category(string $path): View
    {
        $segments = collect(explode('/', trim($path, '/')))
            ->filter()
            ->values();

        abort_unless($segments->count() >= 1 && $segments->count() <= 2, 404);

        if ($segments->count() === 1) {
            $category = Category::query()
                ->where('slug', $segments[0])
                ->whereNull('category_id')
                ->firstOrFail();
        } else {
            $parentSlug = (string) $segments[0];
            $childSlug = (string) $segments[1];

            $parent = Category::query()
                ->where('slug', $parentSlug)
                ->whereNull('category_id')
                ->firstOrFail();

            $category = Category::query()
                ->where('slug', $childSlug)
                ->where('category_id', $parent->id)
                ->firstOrFail();
        }

        $products = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('store.category', [
            'category' => $category,
            'products' => $products,
        ]);
    }

    public function product(string $slug): View
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('category.parent')
            ->firstOrFail();

        $relatedProducts = Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->with('category.parent')
            ->latest()
            ->limit(4)
            ->get();

        return view('store.product', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
