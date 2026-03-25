<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class StoreController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        $featuredProducts = Product::query()
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->limit(8)
            ->get();

        return view('store.index', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
        ]);
    }

    public function category(string $slug): View
    {
        $category = Category::where('slug', $slug)->firstOrFail();

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
            ->with('category')
            ->firstOrFail();

        $relatedProducts = Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->with('category')
            ->latest()
            ->limit(4)
            ->get();

        return view('store.product', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
