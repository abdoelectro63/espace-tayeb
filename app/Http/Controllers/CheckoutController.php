<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ShippingSetting;
use App\Services\ShippingCalculator;
use App\Services\ShoppingCart;
use App\Services\Tracking\FacebookConversionService;
use App\Services\Tracking\TikTokEventService;
use App\Support\Tracking\WebsitePurchaseEventId;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function index(ShoppingCart $cart): View|RedirectResponse
    {
        $cart->syncWithCatalog();
        if ($cart->lines()->isEmpty()) {
            return redirect()->route('store.cart');
        }

        $zone = session('shipping_zone');
        $zone = is_string($zone) && in_array($zone, ['casablanca', 'other'], true) ? $zone : null;
        $settings = ShippingSetting::query()->first();
        $shippingBreakdown = ShippingCalculator::breakdown($cart, $zone, $settings);

        return view('store.checkout', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'shippingBreakdown' => $shippingBreakdown,
            'shippingSettings' => $settings,
            'shippingZone' => $zone,
        ]);
    }

    public function store(Request $request, ShoppingCart $cart): RedirectResponse
    {
        $quickProductId = (int) $request->integer('quick_product_id');
        if ($quickProductId > 0) {
            $quickProduct = Product::query()
                ->where('is_active', true)
                ->with('variations')
                ->find($quickProductId);

            if ($quickProduct === null) {
                return back()
                    ->withErrors(['checkout' => 'المنتج غير متاح حالياً.'])
                    ->withInput();
            }

            $quickQuantity = max(1, (int) $request->integer('quick_quantity', 1));
            $quickVariationId = $request->filled('quick_product_variation_id')
                ? (int) $request->input('quick_product_variation_id')
                : null;

            if ($quickProduct->variations->isNotEmpty() && $quickVariationId !== null) {
                $isValidVariation = $quickProduct->variations
                    ->contains(fn ($variation): bool => (int) $variation->id === $quickVariationId);
                if (! $isValidVariation) {
                    $quickVariationId = null;
                }
            }

            // Buy Now should checkout this product directly, not previous cart lines.
            $cart->clear();
            $cart->add($quickProduct, $quickQuantity, $quickVariationId);
        }

        $cart->syncWithCatalog();
        if ($cart->lines()->isEmpty()) {
            return redirect()
                ->route('store.cart')
                ->withErrors(['checkout' => 'السلة فارغة.']);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'shipping_zone' => ['required', 'in:casablanca,other'],
            'city' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $request->input('shipping_zone') === 'other'),
            ],
        ]);

        $settings = ShippingSetting::query()->first();
        $breakdown = ShippingCalculator::breakdown($cart, $validated['shipping_zone'], $settings);

        if ($breakdown['grand_total'] === null) {
            return back()
                ->withErrors(['shipping_zone' => 'تعذر احتساب المبلغ الإجمالي.'])
                ->withInput();
        }

        $shippingFee = (float) ($breakdown['shipping_fee'] ?? 0);
        $total = (float) $breakdown['grand_total'];

        $cityForOrder = $validated['shipping_zone'] === 'casablanca'
            ? 'الدار البيضاء'
            : trim((string) ($validated['city'] ?? ''));

        $order = DB::transaction(function () use ($validated, $cart, $shippingFee, $total, $cityForOrder) {
            $order = Order::query()->create([
                'number' => $this->generateUniqueOrderNumber(),
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'shipping_address' => $validated['shipping_address'],
                'city' => $cityForOrder,
                'shipping_zone' => $validated['shipping_zone'],
                'shipping_fee' => $shippingFee,
                'total_price' => $total,
                'status' => 'pending',
            ]);

            foreach ($cart->lines() as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $variation = $line['product_variation'] ?? null;
                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variation_id' => $variation?->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $product->finalUnitPriceForCart($variation?->id),
                ]);
            }

            return $order;
        });

        $cart->clear();
        $request->session()->forget('shipping_zone');

        $request->session()->put('checkout_thank_you_order_id', $order->id);

        return redirect()->route('store.checkout.thank-you');
    }

    /**
     * Website checkout completion (thank-you). Use this page for Purchase pixels / server events —
     * not admin "paid" status.
     */
    public function thankYou(Request $request): View|RedirectResponse
    {
        $orderId = $request->session()->get('checkout_thank_you_order_id');

        if (! filled($orderId)) {
            return redirect()->route('store.home');
        }

        $order = Order::query()
            ->with(['orderItems.product'])
            ->find((int) $orderId);

        if ($order === null) {
            $request->session()->forget('checkout_thank_you_order_id');

            return redirect()->route('store.home');
        }

        $order->loadMissing('orderItems.product');

        $eventId = WebsitePurchaseEventId::forOrder($order);
        $clientIp = $request->ip();
        $userAgent = $request->userAgent();

        if ($order->checkout_capi_meta_sent_at === null) {
            try {
                if (app(FacebookConversionService::class)->sendPurchase($order, $eventId, $clientIp, $userAgent)) {
                    $order->forceFill(['checkout_capi_meta_sent_at' => now()])->saveQuietly();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $order->refresh();

        if ($order->checkout_capi_tiktok_sent_at === null) {
            try {
                if (app(TikTokEventService::class)->sendCompletePayment($order, $eventId, $clientIp, $userAgent)) {
                    $order->forceFill(['checkout_capi_tiktok_sent_at' => now()])->saveQuietly();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $order->refresh();

        $trackingPurchase = null;
        if (filled(setting('facebook_pixel_id')) || filled(setting('tiktok_pixel_id'))) {
            $contentIds = $order->orderItems
                ->pluck('product_id')
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->values()
                ->all();
            $contents = [];
            foreach ($order->orderItems as $line) {
                $contents[] = [
                    'content_id' => (string) $line->product_id,
                    'quantity' => max(1, (int) $line->quantity),
                    'price' => round((float) $line->unit_price, 2),
                ];
            }
            $trackingPurchase = [
                'event_id' => $eventId,
                'value' => round((float) $order->total_price, 2),
                'currency' => 'MAD',
                'content_ids' => $contentIds,
                'contents' => $contents,
                'fb_pixel' => filled(setting('facebook_pixel_id')),
                'tt_pixel' => filled(setting('tiktok_pixel_id')),
            ];
        }

        return view('store.checkout-thank-you', [
            'order' => $order,
            'trackingPurchase' => $trackingPurchase,
        ]);
    }

    private function generateUniqueOrderNumber(): string
    {
        do {
            $number = 'ET-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
