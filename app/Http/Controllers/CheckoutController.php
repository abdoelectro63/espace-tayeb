<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ShippingSetting;
use App\Services\ShippingCalculator;
use App\Services\ShoppingCart;
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
                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $product->effectivePrice(),
                ]);
            }

            return $order;
        });

        $cart->clear();
        $request->session()->forget('shipping_zone');

        return redirect()
            ->route('store.home')
            ->with('cart_success', 'تم استلام طلبك. رقم الطلبية: '.$order->number);
    }

    private function generateUniqueOrderNumber(): string
    {
        do {
            $number = 'ET-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
