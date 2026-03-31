<x-layouts.store title="سلة التسوق">
    <div class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <nav class="text-xs text-zinc-500">
                <a href="{{ route('store.home') }}" class="hover:text-emerald-800">الرئيسية</a>
                <span class="mx-2">/</span>
                <span class="text-zinc-800">سلة التسوق</span>
            </nav>
            <h1 class="mt-4 text-3xl font-bold text-zinc-900">سلة التسوق</h1>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        @if($errors->has('cart'))
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">
                {{ $errors->first('cart') }}
            </div>
        @endif

        @if($lines->isEmpty())
            <div class="rounded-2xl border border-dashed border-zinc-200 bg-white p-12 text-center">
                <p class="text-zinc-600">السلة فارغة.</p>
                <a href="{{ route('store.home') }}#products" class="mt-6 inline-flex rounded-full bg-emerald-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800">
                    تابع التسوق
                </a>
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm">
                <ul id="cart-page-lines" class="divide-y divide-zinc-100">
                    @foreach($lines as $line)
                        @php
                            /** @var \App\Models\Product $product */
                            $product = $line['product'];
                            $variation = $line['product_variation'] ?? null;
                            $vid = $variation?->id;
                        @endphp
                        <li
                            class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:gap-6 sm:p-6"
                            data-cart-page-line
                            data-cart-line
                            data-product-id="{{ $product->id }}"
                            data-product-variation-id="{{ $vid ?? '' }}"
                            data-cart-line-key="{{ $product->id }}|{{ $vid ?? 0 }}"
                            data-qty="{{ $line['quantity'] }}"
                            data-stock="{{ $product->maxOrderableQuantity() }}"
                        >
                            <a href="{{ route('store.category', $product->seoRouteParams()) }}" class="shrink-0">
                                <img
                                    src="{{ $product->mainImageUrl() }}"
                                    alt="{{ $product->name }}"
                                    class="h-24 w-24 rounded-xl border border-zinc-100 object-cover sm:h-28 sm:w-28"
                                >
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('store.category', $product->seoRouteParams()) }}" class="font-semibold text-zinc-900 hover:text-emerald-800">
                                    {{ $product->name }}@if($variation)<span class="font-normal text-zinc-600"> — {{ $variation->label() }}</span>@endif
                                </a>
                                @if($product->free_shipping)
                                    <p class="mt-1 text-xs font-medium text-emerald-700">التوصيل مجاني لهذا المنتج</p>
                                @endif
                                <p class="mt-1 text-sm text-zinc-500">
                                    {{ number_format($product->finalUnitPriceForCart($variation?->id), 2) }} MAD / وحدة
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="text-xs text-zinc-500 sm:hidden">الكمية</span>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-lg font-semibold text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40"
                                        data-qty-delta="-1"
                                        data-cart-page-qty
                                        aria-label="تقليل الكمية"
                                    >−</button>
                                    <span data-cart-line-qty class="min-w-[2.25rem] text-center text-sm font-bold text-zinc-900">{{ $line['quantity'] }}</span>
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-200 bg-white text-lg font-semibold text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40"
                                        data-qty-delta="1"
                                        data-cart-page-qty
                                        aria-label="زيادة الكمية"
                                        {{ $line['quantity'] >= $product->maxOrderableQuantity() ? 'disabled' : '' }}
                                    >+</button>
                                </div>
                                <span class="text-xs text-zinc-400">
                                    @if($product->track_stock)
                                        متوفر: {{ $product->stock }}
                                    @else
                                        متوفر
                                    @endif
                                </span>
                            </div>
                            <div class="text-left sm:text-right">
                                <p class="text-lg font-bold text-zinc-900" data-cart-line-total>{{ number_format($line['line_total'], 2) }} <span class="text-sm font-semibold text-zinc-500">MAD</span></p>
                            </div>
                            <form method="post" action="{{ route('store.cart.remove', $product).($vid ? '?product_variation_id='.$vid : '') }}" onsubmit="return confirm('إزالة هذا المنتج من السلة؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-800">
                                    حذف
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>

            @php
                $casFee = $shippingSettings?->casablanca_fee ?? 20;
                $otherFee = $shippingSettings?->other_cities_fee ?? 40;
            @endphp
            <div class="mt-6 rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold text-zinc-900">منطقة التوصيل (المغرب فقط)</p>
                <p class="mt-1 text-xs text-zinc-500">اختر المدينة لاحتساب رسوم التوصيل. الدار البيضاء: {{ number_format((float) $casFee, 0) }} DH — مدن أخرى: {{ number_format((float) $otherFee, 0) }} DH</p>
                <form method="post" action="{{ route('store.cart.shipping-zone') }}" class="mt-3">
                    @csrf
                    <label for="cart-shipping-zone" class="sr-only">المدينة</label>
                    <select
                        id="cart-shipping-zone"
                        name="shipping_zone"
                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm font-medium text-zinc-900"
                        onchange="this.form.submit()"
                    >
                        <option value="" @selected($shippingZone === null)>— اختر المدينة —</option>
                        <option value="casablanca" @selected($shippingZone === 'casablanca')>الدار البيضاء ({{ number_format((float) $casFee, 0) }} DH)</option>
                        <option value="other" @selected($shippingZone === 'other')>مدن أخرى بالمغرب ({{ number_format((float) $otherFee, 0) }} DH)</option>
                    </select>
                </form>
            </div>

            <div class="mt-8 flex flex-col gap-6 rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="w-full space-y-2 sm:max-w-md">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-600">المجموع الفرعي</span>
                        <span id="cart-page-subtotal" class="font-bold text-zinc-900">{{ number_format($subtotal, 2) }} <span class="text-base font-semibold text-zinc-500">MAD</span></span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-600">التوصيل</span>
                        <span id="cart-page-shipping" class="font-bold text-zinc-900">
                            @if(! $shippingBreakdown['requires_paid_shipping'])
                                0.00 <span class="text-base font-semibold text-zinc-500">MAD</span>
                            @elseif($shippingBreakdown['grand_total'] === null)
                                —
                            @else
                                {{ number_format((float) $shippingBreakdown['shipping_fee'], 2) }} <span class="text-base font-semibold text-zinc-500">MAD</span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between border-t border-zinc-100 pt-2 text-sm">
                        <span class="font-semibold text-zinc-800">الإجمالي</span>
                        <span id="cart-page-grand-total" class="text-xl font-bold text-zinc-900">
                            @if($shippingBreakdown['grand_total'] !== null)
                                {{ number_format((float) $shippingBreakdown['grand_total'], 2) }} <span class="text-base font-semibold text-zinc-500">MAD</span>
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <p class="text-xs text-zinc-500">التوصيل داخل المغرب فقط. إن كانت كل المنتجات بتوصيل مجاني، لا تُحسب رسوم التوصيل.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('store.checkout') }}" class="inline-flex justify-center rounded-full bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-emerald-800">
                        إتمام الشراء
                    </a>
                    <a href="{{ route('store.home') }}#products" class="inline-flex justify-center rounded-full border border-zinc-200 px-5 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                        متابعة التسوق
                    </a>
                    <form method="post" action="{{ route('store.cart.clear') }}" onsubmit="return confirm('إفراغ السلة بالكامل؟');">
                        @csrf
                        <button type="submit" class="inline-flex justify-center rounded-full border border-rose-200 px-5 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                            إفراغ السلة
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.store>
