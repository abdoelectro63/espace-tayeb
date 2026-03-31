<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ShippingSetting;
use App\Services\BundleCartService;
use App\Services\ShippingCalculator;
use App\Services\ShoppingCart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

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
            $variation = $line['product_variation'] ?? null;
            $name = $product->name.($variation ? ' — '.$variation->label() : '');

            return [
                'id' => $product->id,
                'product_variation_id' => $variation?->id,
                'name' => $name,
                'slug' => $product->slug,
                'image' => $product->mainImageUrl(),
                'quantity' => $line['quantity'],
                'stock' => $product->maxOrderableQuantity(),
                'track_stock' => (bool) $product->track_stock,
                'line_total' => $line['line_total'],
                'unit_price' => $product->finalUnitPriceForCart($variation?->id),
                'product_url' => route('store.category', $product->seoRouteParams()),
                'free_shipping' => $product->qualifiesForFreeShipping(),
            ];
        })->values()->all();
    }

    public function store(Request $request, ShoppingCart $cart): JsonResponse|RedirectResponse
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::query()
            ->with('variations')
            ->whereKey((int) $request->input('product_id'))
            ->where('is_active', true)
            ->firstOrFail();

        $validated = $request->validate([
            'product_variation_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variations', 'id')->where('product_id', $product->id),
            ],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $quantity = (int) ($validated['quantity'] ?? 1);

        $variationId = isset($validated['product_variation_id'])
            ? (int) $validated['product_variation_id']
            : null;

        if ($product->variations->isNotEmpty()) {
            if ($variationId === null) {
                $variationId = $product->getDefaultVariation()?->id;
            }
            if ($variationId === null || $product->variations->firstWhere('id', $variationId) === null) {
                return $this->addToCartJsonError($request, 'يرجى اختيار نوع المنتج صالح.');
            }
        } else {
            $variationId = null;
        }

        if (! $product->inStock()) {
            return $this->addToCartJsonError($request, 'هذا المنتج غير متوفر حالياً.');
        }

        $currentInCart = $cart->quantity($product->id, $variationId);
        if ($product->track_stock) {
            $stock = (int) ($product->stock ?? 0);
            if ($currentInCart + $quantity > $stock) {
                return $this->addToCartJsonError(
                    $request,
                    'الكمية المطلوبة تتجاوز المخزون المتاح ('.$stock.').'
                );
            }
        }

        $cart->add($product, $quantity, $variationId);

        if ($this->wantsJsonResponse($request)) {
            return $this->addToCartJsonSuccess($cart, 'تمت إضافة المنتج إلى السلة.');
        }

        return back()->with('cart_success', 'تمت إضافة المنتج إلى السلة.');
    }

    /**
     * إضافة المنتج الأساسي + upsell في طلب واحد (كمية موحّدة).
     */
    public function addBundle(Request $request, BundleCartService $bundles): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'primary_product_id' => ['required', 'integer', 'exists:products,id'],
            'upsell_product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $quantity = (int) ($validated['quantity'] ?? 1);

        try {
            $bundles->addBundle(
                (int) $validated['primary_product_id'],
                (int) $validated['upsell_product_id'],
                $quantity,
            );
        } catch (\Throwable $e) {
            return $this->addToCartJsonError($request, $e->getMessage());
        }

        $cart = app(ShoppingCart::class);

        if ($this->wantsJsonResponse($request)) {
            return $this->addToCartJsonSuccess($cart, 'تمت إضافة العرض المجمّع إلى السلة.');
        }

        return back()->with('cart_success', 'تمت إضافة العرض المجمّع إلى السلة.');
    }

    public function update(Request $request, Product $product, ShoppingCart $cart): JsonResponse|RedirectResponse
    {
        $product = Product::query()
            ->with('variations')
            ->where('id', $product->id)
            ->where('is_active', true)
            ->firstOrFail();

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'product_variation_id' => [
                'nullable',
                'integer',
                Rule::exists('product_variations', 'id')->where('product_id', $product->id),
            ],
        ]);

        $variationId = isset($validated['product_variation_id'])
            ? (int) $validated['product_variation_id']
            : null;
        if ($product->variations->isNotEmpty()) {
            if ($variationId === null) {
                $variationId = $product->getDefaultVariation()?->id;
            }
            if ($variationId === null || $product->variations->firstWhere('id', $variationId) === null) {
                return response()->json([
                    'ok' => false,
                    'message' => 'نوع المنتج غير صالح.',
                    'errors' => ['cart' => ['نوع المنتج غير صالح.']],
                ], 422);
            }
        } else {
            $variationId = null;
        }

        $qty = (int) $validated['quantity'];

        if ($qty === 0) {
            $cart->remove($product->id, $variationId);

            if ($this->wantsJsonResponse($request)) {
                return $this->cartStateJsonResponse($cart, 'تمت إزالة المنتج من السلة.');
            }

            return redirect()
                ->route('store.cart')
                ->with('cart_success', 'تمت إزالة المنتج من السلة.');
        }

        if (! $product->inStock()) {
            $cart->remove($product->id, $variationId);

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

        $cart->setQuantity($product, $qty, $variationId);

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

    public function destroy(Request $request, Product $product, ShoppingCart $cart): RedirectResponse
    {
        $variationId = $request->query('product_variation_id');
        $vid = $variationId !== null && $variationId !== '' ? (int) $variationId : null;
        $cart->remove($product->id, $vid);

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
