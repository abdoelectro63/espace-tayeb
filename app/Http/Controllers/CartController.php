<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShippingSetting;
use App\Services\ShippingCalculator;
use App\Services\ShoppingCart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CartController extends Controller
{
    private function wantsJsonResponse(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }

    private function addToCartJsonSuccess(ShoppingCart $cart, string $message): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'cart_count' => $cart->totalQuantity(),
        ]);
    }

    private function addToCartJsonError(Request $request, string $message, int $status = 422): JsonResponse|RedirectResponse
    {
        if ($this->wantsJsonResponse($request)) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'errors' => ['cart' => [$message]],
            ], $status);
        }

        return back()->withErrors(['cart' => $message]);
    }

    public function index(ShoppingCart $cart): View
    {
        $cart->syncWithCatalog();
        $zone = session('shipping_zone');
        $zone = is_string($zone) && in_array($zone, ['casablanca', 'other'], true) ? $zone : null;
        $settings = ShippingSetting::query()->first();
        $shippingBreakdown = ShippingCalculator::breakdown($cart, $zone, $settings);

        return view('store.cart', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'shippingBreakdown' => $shippingBreakdown,
            'shippingSettings' => $settings,
            'shippingZone' => $zone,
        ]);
    }

    /**
     * Store selected delivery zone (Casablanca vs other Morocco cities) for cart totals.
     */
    public function setShippingZone(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'shipping_zone' => ['nullable', 'in:casablanca,other'],
        ]);

        $zone = $validated['shipping_zone'] ?? null;
        if ($zone === null || $zone === '') {
            $request->session()->forget('shipping_zone');
        } else {
            $request->session()->put('shipping_zone', $zone);
        }

        if ($this->wantsJsonResponse($request)) {
            $cart = app(ShoppingCart::class);
            $cart->syncWithCatalog();

            return response()->json([
                'ok' => true,
                'shipping_zone' => $zone,
                ...$this->jsonShippingPayload($cart),
                'items' => $this->mapCartLinesToJson($cart->lines()),
                'subtotal' => $cart->subtotal(),
                'cart_count' => $cart->totalQuantity(),
            ]);
        }

        return back();
    }

    /**
     * JSON for the mini-cart drawer (line items + totals).
     */
    public function drawer(ShoppingCart $cart): JsonResponse
    {
        $cart->syncWithCatalog();
        $lines = $cart->lines();

        return response()->json([
            'items' => $this->mapCartLinesToJson($lines),
            'subtotal' => $cart->subtotal(),
            'count' => $cart->totalQuantity(),
            ...$this->jsonShippingPayload($cart),
        ]);
    }

    /**
     * @param  Collection<int, array{product: Product, quantity: int, line_total: float}>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function mapCartLinesToJson(Collection $lines): array
    {
        return $lines->map(function (array $line): array {
            $product = $line['product'];

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'image' => $product->mainImageUrl(),
                'quantity' => $line['quantity'],
                'stock' => $product->maxOrderableQuantity(),
                'track_stock' => (bool) $product->track_stock,
                'line_total' => $line['line_total'],
                'product_url' => route('store.product', $product->slug),
                'free_shipping' => (bool) $product->free_shipping,
            ];
        })->values()->all();
    }

    public function store(Request $request, ShoppingCart $cart): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $quantity = (int) ($validated['quantity'] ?? 1);

        $product = Product::query()
            ->where('id', $validated['product_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $product->inStock()) {
            return $this->addToCartJsonError($request, 'هذا المنتج غير متوفر حالياً.');
        }

        $currentInCart = $cart->quantity($product->id);
        if ($product->track_stock) {
            $stock = (int) ($product->stock ?? 0);
            if ($currentInCart + $quantity > $stock) {
                return $this->addToCartJsonError(
                    $request,
                    'الكمية المطلوبة تتجاوز المخزون المتاح ('.$stock.').'
                );
            }
        }

        $cart->add($product, $quantity);

        if ($this->wantsJsonResponse($request)) {
            return $this->addToCartJsonSuccess($cart, 'تمت إضافة المنتج إلى السلة.');
        }

        return back()->with('cart_success', 'تمت إضافة المنتج إلى السلة.');
    }

    public function update(Request $request, Product $product, ShoppingCart $cart): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $product = Product::query()
            ->where('id', $product->id)
            ->where('is_active', true)
            ->firstOrFail();

        $qty = (int) $validated['quantity'];

        if ($qty === 0) {
            $cart->remove($product->id);

            if ($this->wantsJsonResponse($request)) {
                return $this->cartStateJsonResponse($cart, 'تمت إزالة المنتج من السلة.');
            }

            return redirect()
                ->route('store.cart')
                ->with('cart_success', 'تمت إزالة المنتج من السلة.');
        }

        if (! $product->inStock()) {
            $cart->remove($product->id);

            if ($this->wantsJsonResponse($request)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'هذا المنتج لم يعد متوفراً.',
                    'errors' => ['cart' => ['هذا المنتج لم يعد متوفراً.']],
                ], 422);
            }

            return redirect()
                ->route('store.cart')
                ->withErrors(['cart' => 'هذا المنتج لم يعد متوفراً.']);
        }

        if ($product->track_stock && $qty > (int) $product->stock) {
            $message = 'الكمية المطلوبة تتجاوز المخزون المتاح ('.$product->stock.').';

            if ($this->wantsJsonResponse($request)) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'errors' => ['cart' => [$message]],
                ], 422);
            }

            return redirect()
                ->route('store.cart')
                ->withErrors(['cart' => $message]);
        }

        $cart->setQuantity($product, $qty);

        if ($this->wantsJsonResponse($request)) {
            return $this->cartStateJsonResponse($cart, 'تم تحديث السلة.');
        }

        return redirect()
            ->route('store.cart')
            ->with('cart_success', 'تم تحديث السلة.');
    }

    private function cartStateJsonResponse(ShoppingCart $cart, string $message): JsonResponse
    {
        $cart->syncWithCatalog();
        $lines = $cart->lines();

        return response()->json([
            'ok' => true,
            'message' => $message,
            'cart_count' => $cart->totalQuantity(),
            'subtotal' => $cart->subtotal(),
            'items' => $this->mapCartLinesToJson($lines),
            ...$this->jsonShippingPayload($cart),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonShippingPayload(ShoppingCart $cart): array
    {
        $zone = session('shipping_zone');
        $zone = is_string($zone) && in_array($zone, ['casablanca', 'other'], true) ? $zone : null;
        $settings = ShippingSetting::query()->first();
        $b = ShippingCalculator::breakdown($cart, $zone, $settings);

        return [
            'shipping_zone' => $zone,
            'requires_paid_shipping' => $b['requires_paid_shipping'],
            'zone_selected' => $b['zone_selected'],
            'shipping_fee' => $b['shipping_fee'],
            'grand_total' => $b['grand_total'],
        ];
    }

    public function destroy(Product $product, ShoppingCart $cart): RedirectResponse
    {
        $cart->remove($product->id);

        return redirect()
            ->route('store.cart')
            ->with('cart_success', 'تمت إزالة المنتج من السلة.');
    }

    public function clear(ShoppingCart $cart): RedirectResponse
    {
        $cart->clear();

        return redirect()
            ->route('store.cart')
            ->with('cart_success', 'تم إفراغ السلة.');
    }
}
