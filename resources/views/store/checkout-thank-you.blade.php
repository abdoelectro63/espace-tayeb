@php
    /** @var \App\Models\Order $order */
    $lineItems = $order->orderItems;
@endphp
<x-layouts.store :title="'تم تأكيد الطلبية'">
    <div class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <nav class="text-xs text-zinc-500">
                <a href="{{ route('store.home') }}" class="hover:text-emerald-800">الرئيسية</a>
                <span class="mx-2">/</span>
                <span class="text-zinc-800">شكراً لك</span>
            </nav>
            <h1 class="mt-4 text-3xl font-bold text-zinc-900">تم استلام طلبك</h1>
            <p class="mt-2 text-sm text-zinc-600">
                رقم الطلبية: <span class="font-semibold text-zinc-900">{{ $order->number }}</span>
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div class="overflow-hidden rounded-2xl border border-emerald-100 bg-emerald-50/50 p-6 sm:p-8">
            <p class="text-sm text-zinc-700">
                شكراً على ثقتك. سنتواصل معك قريباً لتأكيد الطلبية والتوصيل.
            </p>
            <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-zinc-500">المجموع</dt>
                    <dd class="font-semibold text-zinc-900">{{ number_format((float) $order->total_price, 2) }} MAD</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">الهاتف</dt>
                    <dd class="font-semibold text-zinc-900" dir="ltr">{{ $order->customer_phone }}</dd>
                </div>
            </dl>
        </div>

        @if($lineItems->isNotEmpty())
            <div class="mt-8 overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm">
                <div class="border-b border-zinc-100 px-4 py-3 text-sm font-semibold text-zinc-900">المنتجات</div>
                <ul class="divide-y divide-zinc-100">
                    @foreach($lineItems as $item)
                        @php $product = $item->product; @endphp
                        <li class="flex gap-4 p-4 sm:p-5">
                            @if($product)
                                <img src="{{ $product->mainImageUrl() }}" alt="" class="h-16 w-16 shrink-0 rounded-lg border border-zinc-100 object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-zinc-900">{{ $product->name }}</p>
                                    <p class="mt-1 text-sm text-zinc-500">الكمية: {{ $item->quantity }}</p>
                                </div>
                            @else
                                <div class="text-sm text-zinc-600">منتج غير متوفر</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-10 flex flex-wrap gap-4">
            <a href="{{ route('store.home') }}" class="inline-flex items-center justify-center rounded-full bg-[#ff751f] px-6 py-3 text-sm font-semibold text-white shadow transition hover:bg-orange-600">
                العودة للمتجر
            </a>
        </div>
    </div>

    @if(! empty($trackingPurchase))
        @push('scripts')
            <script>
                (function () {
                    var p = @json($trackingPurchase);
                    var key = 'purchase_tracked_' + p.event_id;
                    try {
                        if (sessionStorage.getItem(key)) {
                            return;
                        }
                        sessionStorage.setItem(key, '1');
                    } catch (e) {
                        return;
                    }

                    if (p.fb_pixel && typeof fbq === 'function') {
                        fbq('track', 'Purchase', {
                            value: p.value,
                            currency: p.currency,
                            content_ids: p.content_ids,
                            content_type: 'product'
                        }, { eventID: p.event_id });
                    }

                    if (! p.tt_pixel) {
                        return;
                    }

                    function sendTikTok(attempts) {
                        if (typeof ttq === 'undefined' || ! ttq || typeof ttq.track !== 'function') {
                            if (attempts < 40) {
                                setTimeout(function () { sendTikTok(attempts + 1); }, 100);
                            }
                            return;
                        }
                        ttq.track('CompletePayment', {
                            value: p.value,
                            currency: p.currency,
                            contents: p.contents,
                            event_id: p.event_id
                        });
                    }

                    sendTikTok(0);
                })();
            </script>
        @endpush
    @endif
</x-layouts.store>
