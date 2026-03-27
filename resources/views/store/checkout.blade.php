@php
    $casFee = $shippingSettings?->casablanca_fee ?? 20;
    $otherFee = $shippingSettings?->other_cities_fee ?? 40;
@endphp
<x-layouts.store title="إتمام الشراء">
    <div class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <nav class="text-xs text-zinc-500">
                <a href="{{ route('store.home') }}" class="hover:text-emerald-800">الرئيسية</a>
                <span class="mx-2">/</span>
                <a href="{{ route('store.cart') }}" class="hover:text-emerald-800">السلة</a>
                <span class="mx-2">/</span>
                <span class="text-zinc-800">الدفع</span>
            </nav>
            <h1 class="mt-4 text-3xl font-bold text-zinc-900">إتمام الشراء</h1>
            <p class="mt-2 text-sm text-zinc-600">التوصيل داخل المغرب فقط. اختر الدار البيضاء أو مدينة أخرى، ثم أكمل بياناتك.</p>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if($lines->isEmpty())
            <div class="rounded-2xl border border-dashed border-zinc-200 bg-white p-12 text-center text-zinc-600">
                السلة فارغة.
                <a href="{{ route('store.home') }}#products" class="mt-4 inline-block font-semibold text-emerald-800 hover:underline">تصفح المنتجات</a>
            </div>
        @else
            @if($errors->any())
                <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="post" action="{{ route('store.checkout.store') }}" class="grid gap-10 lg:grid-cols-3" id="checkout-form">
                @csrf
                <div class="lg:col-span-2 space-y-6">
                    <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm">
                        <div class="border-b border-zinc-100 px-4 py-3 text-sm font-semibold text-zinc-900">المنتجات</div>
                        <ul class="divide-y divide-zinc-100">
                            @foreach($lines as $line)
                                @php $product = $line['product']; @endphp
                                <li class="flex gap-4 p-4 sm:p-5">
                                    <a href="{{ route('product.show', $product->seoRouteParams()) }}" class="shrink-0">
                                        <img src="{{ $product->mainImageUrl() }}" alt="" class="h-20 w-20 rounded-lg border border-zinc-100 object-cover sm:h-24 sm:w-24">
                                    </a>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('product.show', $product->seoRouteParams()) }}" class="font-semibold text-zinc-900 hover:text-emerald-800">{{ $product->name }}</a>
                                        @if($product->free_shipping)
                                            <p class="mt-1 text-xs font-medium text-emerald-700">التوصيل مجاني لهذا المنتج</p>
                                        @endif
                                        <p class="mt-1 text-sm text-zinc-500">الكمية: {{ $line['quantity'] }}</p>
                                        <p class="mt-2 text-sm font-bold text-zinc-900">{{ number_format($line['line_total'], 2) }} MAD</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-zinc-900">المدينة والتوصيل</h2>
                        <p class="mt-2 text-sm text-zinc-600">
                            <span class="font-medium text-zinc-800">الدار البيضاء:</span> يُضاف تلقائياً {{ number_format((float) $casFee, 0) }} DH للتوصيل.
                            <span class="mx-1 text-zinc-400">|</span>
                            <span class="font-medium text-zinc-800">مدينة أخرى:</span> {{ number_format((float) $otherFee, 0) }} DH — اكتب اسم مدينتك أدناه.
                        </p>

                        <label for="checkout-shipping-zone" class="mt-5 block text-sm font-medium text-zinc-700">أين نوصل طلبك؟</label>
                        <select
                            id="checkout-shipping-zone"
                            name="shipping_zone"
                            required
                            class="mt-2 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm font-medium text-zinc-900"
                            data-shipping-sync-url="{{ route('store.cart.shipping-zone', [], false) }}"
                        >
                            <option value="" disabled @selected($shippingZone === null)>— اختر: الدار البيضاء أو مدينة أخرى —</option>
                            <option value="casablanca" @selected(old('shipping_zone', $shippingZone) === 'casablanca')>
                                الدار البيضاء — توصيل {{ number_format((float) $casFee, 0) }} DH
                            </option>
                            <option value="other" @selected(old('shipping_zone', $shippingZone) === 'other')>
                                مدينة أخرى بالمغرب — توصيل {{ number_format((float) $otherFee, 0) }} DH
                            </option>
                        </select>

                        @php
                            $showOtherCity = old('shipping_zone', $shippingZone) === 'other';
                        @endphp
                        <div id="checkout-other-city-wrap" class="mt-4 {{ $showOtherCity ? '' : 'hidden' }}">
                            <label for="checkout-city-other" class="block text-sm font-medium text-zinc-700">اسم المدينة <span class="text-rose-600">*</span></label>
                            <input
                                id="checkout-city-other"
                                name="city"
                                type="text"
                                value="{{ old('city') }}"
                                placeholder="مثال: الرباط، طنجة، أكادير…"
                                class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2.5 text-sm"
                                autocomplete="address-level2"
                                @if($showOtherCity) required @endif
                            >
                            @error('city')
                                <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-zinc-900">بيانات التوصيل</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="customer_name" class="block text-sm font-medium text-zinc-700">الاسم الكامل</label>
                                <input id="customer_name" name="customer_name" type="text" required value="{{ old('customer_name') }}" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2.5 text-sm" autocomplete="name">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="customer_phone" class="block text-sm font-medium text-zinc-700">الهاتف</label>
                                <input id="customer_phone" name="customer_phone" type="tel" required value="{{ old('customer_phone') }}" class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2.5 text-sm" autocomplete="tel">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="shipping_address" class="block text-sm font-medium text-zinc-700">عنوان التوصيل الكامل</label>
                                <textarea id="shipping_address" name="shipping_address" rows="3" required class="mt-1 w-full rounded-xl border border-zinc-200 px-3 py-2.5 text-sm">{{ old('shipping_address') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="sticky top-24 space-y-4 rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-600">المجموع الفرعي</span>
                            <span id="checkout-summary-subtotal" class="font-bold text-zinc-900">{{ number_format($subtotal, 2) }} <span class="text-zinc-500">MAD</span></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-600">التوصيل</span>
                            <span id="checkout-summary-shipping" class="font-bold text-zinc-900">
                                @if(! $shippingBreakdown['requires_paid_shipping'])
                                    0.00 MAD
                                @elseif($shippingBreakdown['grand_total'] === null)
                                    —
                                @else
                                    {{ number_format((float) $shippingBreakdown['shipping_fee'], 2) }} MAD
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-t border-zinc-100 pt-3 text-sm">
                            <span class="font-semibold text-zinc-800">الإجمالي</span>
                            <span id="checkout-summary-grand" class="text-xl font-bold text-zinc-900">
                                @if($shippingBreakdown['grand_total'] !== null)
                                    {{ number_format((float) $shippingBreakdown['grand_total'], 2) }} <span class="text-base font-semibold text-zinc-500">MAD</span>
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <p class="text-xs text-zinc-500">اختر الدار البيضاء ({{ number_format((float) $casFee, 0) }} DH) أو مدينة أخرى ({{ number_format((float) $otherFee, 0) }} DH) ليظهر المجموع. إن كانت كل المنتجات بتوصيل مجاني، لا تُحسب رسوم التوصيل.</p>
                        <button type="submit" class="w-full rounded-full bg-emerald-700 py-3 text-sm font-semibold text-white shadow transition hover:bg-emerald-800">
                            تأكيد الطلب
                        </button>
                        <a href="{{ route('store.cart') }}" class="block w-full rounded-full border border-zinc-200 py-3 text-center text-sm font-semibold text-zinc-700 hover:bg-zinc-50">
                            تعديل السلة
                        </a>
                    </div>
                </div>
            </form>

            <script>
                (function () {
                    const select = document.getElementById('checkout-shipping-zone');
                    const wrap = document.getElementById('checkout-other-city-wrap');
                    const cityInput = document.getElementById('checkout-city-other');
                    const syncUrl = select?.dataset?.shippingSyncUrl;
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const elSub = document.getElementById('checkout-summary-subtotal');
                    const elShip = document.getElementById('checkout-summary-shipping');
                    const elGrand = document.getElementById('checkout-summary-grand');

                    function toggleOtherCity() {
                        if (!select || !wrap || !cityInput) return;
                        const isOther = select.value === 'other';
                        wrap.classList.toggle('hidden', !isOther);
                        cityInput.required = isOther;
                        if (!isOther) {
                            cityInput.value = '';
                        }
                    }

                    function madSpan(amount) {
                        const n = Number(amount);
                        const v = Number.isFinite(n) ? n.toFixed(2) : '0.00';
                        return v + ' <span class="text-zinc-500">MAD</span>';
                    }

                    function updateSummaryFromJson(data) {
                        if (!data || !elSub || !elShip || !elGrand) return;
                        if (data.subtotal !== undefined) {
                            elSub.innerHTML = madSpan(data.subtotal);
                        }
                        if (data.requires_paid_shipping === false) {
                            elShip.textContent = '0.00 MAD';
                            const gFree = data.grand_total != null ? data.grand_total : data.subtotal ?? 0;
                            elGrand.innerHTML = madSpan(gFree);
                            return;
                        }
                        if (data.zone_selected === false || data.shipping_fee === null || data.shipping_fee === undefined) {
                            elShip.textContent = '—';
                            elGrand.textContent = '—';
                            return;
                        }
                        elShip.textContent = Number(data.shipping_fee).toFixed(2) + ' MAD';
                        const g = data.grand_total != null ? data.grand_total : Number(data.subtotal) + Number(data.shipping_fee);
                        elGrand.innerHTML = madSpan(g);
                    }

                    select?.addEventListener('change', function () {
                        toggleOtherCity();
                        if (!syncUrl || !token) return;
                        const fd = new FormData();
                        fd.append('_token', token);
                        fd.append('shipping_zone', this.value || '');
                        fetch(syncUrl, {
                            method: 'POST',
                            body: fd,
                            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                            credentials: 'same-origin',
                        })
                            .then(function (r) {
                                return r.json();
                            })
                            .then(function (data) {
                                updateSummaryFromJson(data);
                            })
                            .catch(function () {});
                    });

                    toggleOtherCity();
                })();
            </script>
        @endif
    </div>
</x-layouts.store>
