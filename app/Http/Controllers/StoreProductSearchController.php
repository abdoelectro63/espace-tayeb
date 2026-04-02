<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreProductSearchController extends Controller
{
    /**
     * JSON search for storefront header (product name / code).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['products' => []]);
        }

        $term = '%'.$q.'%';

        $products = Product::query()
            ->where('is_active', true)
            ->whereHas('category')
            ->with(['category.parent'])
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            })
            ->latest()
            ->limit(10)
            ->get();

        $payload = $products->map(function (Product $product): array {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'url' => route('store.category', $product->seoRouteParams()),
                'image' => $product->mainImageUrl(),
                'price' => number_format($product->effectivePrice(), 2, '.', ' ').' MAD',
                'price_value' => $product->effectivePrice(),
            ];
        })->values()->all();

        return response()->json(['products' => $payload]);
    }
}
