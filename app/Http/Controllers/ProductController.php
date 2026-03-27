<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function show(string $categoryPath, Product $product): View|RedirectResponse
    {
        abort_unless((bool) $product->is_active, 404);

        $product->loadMissing('category.parent');

        $expectedPath = $product->category?->storePath();
        abort_if(blank($expectedPath), 404);

        if (trim($categoryPath, '/') !== trim($expectedPath, '/')) {
            return redirect()->route('product.show', $product->seoRouteParams(), 301);
        }

        return $this->renderShowPage($product);
    }

    private function renderShowPage(Product $product): View
    {
        $product->loadMissing('category.parent');

        $relatedProducts = Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->with('category.parent')
            ->latest()
            ->limit(4)
            ->get();

        return view('products.show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
        ]);
    }
}
