@php
    $productBreadcrumbItems = [
        ['name' => 'الرئيسية', 'url' => route('store.home')],
    ];

    if ($product->category?->parent) {
        $productBreadcrumbItems[] = [
            'name' => $product->category->parent->name,
            'url' => route('store.category', ['path' => $product->category->parent->storePath()]),
        ];
    }

    if ($product->category) {
        $productBreadcrumbItems[] = [
            'name' => $product->category->name,
            'url' => route('store.category', ['path' => $product->category->storePath()]),
        ];
    }

    $productBreadcrumbItems[] = [
        'name' => $product->name,
        'url' => route('store.category', $product->seoRouteParams()),
    ];
@endphp

<x-layouts.store
    :title="$product->seoTitle()"
    :metaDescription="$product->seoDescription()"
    :canonical="route('store.category', $product->seoRouteParams())"
    :breadcrumbItems="$productBreadcrumbItems"
>
    <div class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <nav class="text-xs text-zinc-500">
                <a href="{{ route('store.home') }}" class="hover:text-emerald-800">الرئيسية</a>
                <span class="mx-2">/</span>
                @if($product->category)
                    @if($product->category->parent)
                        <a href="{{ route('store.category', ['path' => $product->category->parent->storePath()]) }}" class="hover:text-emerald-800">{{ $product->category->parent->name }}</a>
                        <span class="mx-2">/</span>
                    @endif
                    <a href="{{ route('store.category', ['path' => $product->category->storePath()]) }}" class="hover:text-emerald-800">{{ $product->category->name }}</a>
                    <span class="mx-2">/</span>
                @endif
                <span class="text-zinc-800">{{ $product->name }}</span>
            </nav>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-12 pb-12 sm:px-6">
        <div class="grid gap-10 lg:grid-cols-2 lg:gap-14">
            <div class="space-y-4">
                <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm">
                    <img
                        data-main-product-img
                        src="{{ $product->mainImageUrl() }}"
                        alt="{{ $product->name }}"
                        class="aspect-square w-full object-cover"
                        onerror="this.onerror=null;this.src='{{ asset('images/placeholder-product.svg') }}';"
                    >
                </div>
                @php
                    $gallery = $product->galleryImageUrls();
                @endphp
                @if(count($gallery) > 0)
                    <div class="grid grid-cols-4 gap-2 sm:gap-3">
                        @foreach($gallery as $url)
                            <button type="button" class="overflow-hidden rounded-lg border border-zinc-100 bg-zinc-50" onclick="document.querySelector('[data-main-product-img]').src='{{ $url }}'">
                                <img src="{{ $url }}" alt="" class="aspect-square w-full object-cover" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('images/placeholder-product.svg') }}';">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                @if($product->category)
                    <p class="text-sm font-medium text-orange-700">{{ $product->category->name }}</p>
                @endif
                <h1 class="mt-2 text-3xl font-bold text-zinc-900 sm:text-4xl">{{ $product->name }}</h1>

                @php
                    $hasVariations = $product->variations->isNotEmpty();
                    $defaultVariation = $product->getDefaultVariation();
                    $variationOptions = $hasVariations
                        ? $product->variations->map(fn ($v) => [
                            'id' => $v->id,
                            'label' => $v->label(),
                            'price' => $product->finalUnitPriceForCart($v->id),
                        ])->values()->all()
                        : [];
                @endphp

                @if(! $hasVariations)
                    @php
                        $payPrice = (float) $product->final_price;
                        $effectivePrice = $product->effectivePrice();
                        $listPrice = (float) $product->price;
                    @endphp
                    <div class="mt-6 flex flex-wrap items-baseline gap-3">
                        <span class="product-price-new product-price-new--lg">
                            {{ number_format($payPrice, 2) }}
                            <span class="product-price-currency">MAD</span>
                        </span>
                        @if($effectivePrice > $payPrice + 0.001)
                            <span class="product-price-old">{{ number_format($effectivePrice, 2) }}</span>
                        @endif
                        @if($listPrice > $effectivePrice + 0.001)
                            <span class="product-price-old">{{ number_format($listPrice, 2) }}</span>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($product->hasActivePercentageOffer())
                            @php $offerPct = $product->displayOfferPercentage(); @endphp
                            @if($offerPct !== null)
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">
                                    خصم إضافي {{ rtrim(rtrim(number_format($offerPct, 2), '0'), '.') }}٪
                                </span>
                            @endif
                        @endif
                        @if(filled($product->discount_price) && (float) $product->discount_price < (float) $product->price)
                            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-100">تخفيض</span>
                        @endif
                    </div>
                @endif

                @if($hasVariations)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($product->hasActivePercentageOffer())
                            @php $offerPct = $product->displayOfferPercentage(); @endphp
                            @if($offerPct !== null)
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">خصم إضافي {{ rtrim(rtrim(number_format($offerPct, 2), '0'), '.') }}٪</span>
                            @endif
                        @endif
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap gap-3 text-sm">
                    @if($product->qualifiesForFreeShipping())
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 font-medium text-emerald-800 ring-1 ring-emerald-200/80">
                            توصيل مجاني
                        </span>
                    @endif
                    @if($product->inStock())
                        <span class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1.5 font-medium text-orange-700">
                            <span class="h-2 w-2 rounded-full bg-orange-500"></span>
                            @if($product->track_stock)
                                متوفر ({{ $product->stock }})
                            @else
                                متوفر
                            @endif
                        </span>
                    @else
                        <span class="inline-flex items-center gap-2 rounded-full bg-zinc-100 px-3 py-1.5 font-medium text-zinc-700">
                            غير متوفر حالياً
                        </span>
                    @endif
                </div>

                @if(filled($product->description))
                    <div class="mt-8 text-sm leading-relaxed text-zinc-700">
                        {!! nl2br(e($product->description)) !!}
                    </div>
                @endif

                @error('cart')
                    <div class="mt-8 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">
                        {{ $message }}
                    </div>
                @enderror

                @if($product->inStock())
                    @if($hasVariations)
                        <div
                            class="mt-10"
                            x-data="{
                                selectedId: {{ $defaultVariation?->id ?? 'null' }},
                                options: {{ \Illuminate\Support\Js::from($variationOptions) }},
                                get linePrice() {
                                    const o = this.options.find(x => Number(x.id) === Number(this.selectedId));
                                    return o ? Number(o.price) : 0;
                                }
                            }"
                        >
                    @endif
                    <form
                        id="product-add-cart-form"
                        method="post"
                        action="{{ route('store.cart.add') }}"
                        class="{{ $hasVariations ? '' : 'mt-10 ' }}flex flex-col gap-4 rounded-2xl border border-orange-100 bg-orange-50/50 p-6 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between"
                        data-add-to-cart
                        data-fly-image="{{ e($product->mainImageUrl()) }}"
                        data-fly-source-selector="[data-main-product-img]"
                    >
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        @if($hasVariations)
                            <div class="w-full space-y-3 sm:order-first">
                                <div>
                                    <label for="product-variation-select" class="text-sm font-semibold text-zinc-900">النوع</label>
                                    <select
                                        id="product-variation-select"
                                        name="product_variation_id"
                                        x-model.number="selectedId"
                                        class="mt-2 w-full max-w-md rounded-lg border border-zinc-200 bg-white px-3 py-2.5 text-sm font-medium text-zinc-900"
                                    >
                                        @foreach($product->variations as $v)
                                            <option value="{{ $v->id }}">{{ $v->label() }} — {{ number_format($product->finalUnitPriceForCart($v->id), 2) }} MAD</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex flex-wrap items-baseline gap-3">
                                    <span class="product-price-new product-price-new--md">
                                        <span x-text="linePrice.toFixed(2)"></span>
                                        <span class="product-price-currency">MAD</span>
                                    </span>
                                </div>
                            </div>
                        @endif
                        <div class="flex flex-col gap-2">
                            <label for="cart-quantity" class="text-sm font-semibold text-zinc-900">الكمية</label>
                            <input
                                id="cart-quantity"
                                type="number"
                                name="quantity"
                                value="1"
                                min="1"
                                max="{{ $product->maxOrderableQuantity() }}"
                                class="w-28 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-center text-sm font-medium"
                            >
                            <p class="text-xs text-zinc-500">
                                @if($product->track_stock)
                                    متوفر: {{ $product->stock }}
                                @else
                                    متوفر
                                @endif
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="inline-flex w-full min-h-[52px] items-center justify-center gap-2.5 rounded-full bg-[#ff751f] px-8 py-4 text-base font-semibold text-white shadow-md transition hover:bg-orange-600 sm:w-auto sm:min-w-[200px]"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138M8.25 20.25a.75.75 0 100-1.5.75.75 0 000 1.5zm9 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                            </svg>
                            <span>أضف إلى السلة</span>
                        </button>
                    </form>
                    @if($hasVariations)
                        </div>
                    @endif
                @endif

                @php
                    $upsell = $product->upsellProduct;
                    $showBundle = $upsell
                        && $product->inStock()
                        && $upsell->inStock()
                        && $upsell->is_active;
                    $bundleMaxQty = $showBundle ? min($product->maxOrderableQuantity(), $upsell->maxOrderableQuantity()) : 0;
                @endphp
                @if($showBundle && $bundleMaxQty > 0)
                    @php
                        $bundlePct = $product->bundleUpsellPercentageForDisplay();
                    @endphp
                    <div class="mt-10 overflow-hidden rounded-2xl border border-orange-200 bg-gradient-to-br from-orange-50/90 to-white shadow-sm ring-1 ring-orange-100">
                        <div class="flex items-center gap-3 border-b border-orange-100/80 bg-gradient-to-l from-[#ff751f]/10 to-transparent px-4 py-3 sm:px-5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#ff751f] text-white shadow-md shadow-orange-500/25 ring-2 ring-white" title="عرض ترويجي">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-base font-bold text-zinc-900">الهزة المنتظرة </p>
                                <p class="text-xs font-medium text-orange-800/90">عرض خاص — وفر على المنتج المقترَح</p>
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="flex flex-wrap items-center justify-center gap-3 sm:gap-5">
                                <div class="flex flex-col items-center gap-2">
                                    <div class="relative h-24 w-24 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm sm:h-28 sm:w-28">
                                        <img
                                            src="{{ $product->mainImageUrl() }}"
                                            alt=""
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                            decoding="async"
                                            onerror="this.onerror=null;this.src='{{ asset('images/placeholder-product.svg') }}';"
                                        >
                                    </div>
                                    <span class="max-w-[8.5rem] text-center text-xs font-medium text-zinc-600 line-clamp-2">{{ $product->name }}</span>
                                </div>
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-200/80 text-lg font-bold text-zinc-500" aria-hidden="true">+</span>
                                <div class="flex flex-col items-center gap-2">
                                    <div class="relative">
                                        @if($bundlePct !== null)
                                            <span class="absolute -right-1 -top-1 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-amber-400 text-[10px] font-bold text-amber-950 shadow ring-2 ring-white" title="تخفيض">
                                                %
                                            </span>
                                        @elseif($product->offer_type === \App\Models\Product::OFFER_FREE_DELIVERY)
                                            <span class="absolute -right-1 -top-1 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white shadow ring-2 ring-white" title="توصيل مجاني">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-12m2.25-4.5H9.375a1.125 1.125 0 01-1.125-1.125V9.375a1.125 1.125 0 011.125-1.125h3.75a1.125 1.125 0 011.125 1.125v3.75a1.125 1.125 0 01-1.125 1.125z" />
                                                </svg>
                                            </span>
                                        @endif
                                        <div class="h-24 w-24 overflow-hidden rounded-xl border-2 border-dashed border-[#ff751f]/50 bg-white shadow-md ring-2 ring-orange-100 sm:h-28 sm:w-28">
                                            <img
                                                src="{{ $upsell->mainImageUrl() }}"
                                                alt="{{ $upsell->name }}"
                                                class="h-full w-full object-cover"
                                                loading="lazy"
                                                decoding="async"
                                                onerror="this.onerror=null;this.src='{{ asset('images/placeholder-product.svg') }}';"
                                            >
                                        </div>
                                    </div>
                                    <span class="max-w-[8.5rem] text-center text-xs font-semibold text-zinc-800 line-clamp-2">{{ $upsell->name }}</span>
                                </div>
                            </div>
                            <p class="mt-4 text-center text-xs text-zinc-500">
                                يُضاف المنتجان معاً إلى السلة بنفس الكمية.
                            </p>
                            @if($bundlePct !== null)
                                <p class="mt-2 text-center text-xs font-medium text-amber-900">
                                    خصم {{ rtrim(rtrim(number_format($bundlePct, 2), '0'), '.') }}٪ على {{ $upsell->name }}
                                </p>
                            @endif
                            @if($product->offer_type === \App\Models\Product::OFFER_FREE_DELIVERY)
                                <p class="mt-1 flex items-center justify-center gap-1.5 text-center text-xs font-medium text-emerald-800">
                                    <svg class="h-4 w-4 shrink-0 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-12m2.25-4.5H9.375a1.125 1.125 0 01-1.125-1.125V9.375a1.125 1.125 0 011.125-1.125h3.75a1.125 1.125 0 011.125 1.125v3.75a1.125 1.125 0 01-1.125 1.125z" />
                                    </svg>
                                    توصيل مجاني يطبق على {{ $upsell->name }}
                                </p>
                            @endif
                            <form
                            method="post"
                            action="{{ route('store.cart.add_bundle') }}"
                            class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                            data-add-to-cart
                            data-fly-image="{{ e($product->mainImageUrl()) }}"
                            data-fly-source-selector="[data-main-product-img]"
                        >
                            @csrf
                            <input type="hidden" name="primary_product_id" value="{{ $product->id }}">
                            <input type="hidden" name="upsell_product_id" value="{{ $upsell->id }}">
                          <!--  <div class="flex flex-col gap-2">
                                <label for="bundle-quantity" class="text-sm font-semibold text-zinc-900">الكمية</label>
                                <input
                                    id="bundle-quantity"
                                    type="number"
                                    name="quantity"
                                    value="1"
                                    min="1"
                                    max="{{ $bundleMaxQty }}"
                                    class="w-28 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-center text-sm font-medium"
                                >
                            </div>-->
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-zinc-900 px-8 py-3 text-sm font-semibold text-white shadow transition hover:bg-zinc-800 sm:w-auto">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138M8.25 20.25a.75.75 0 100-1.5.75.75 0 000 1.5zm9 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                                </svg>
                                <span>اشتري الاثنين و احصل على تخفيض</span>
                            </button>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="mt-10 rounded-2xl border border-zinc-100 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold text-zinc-900">للطلب والاستفسار</p>
                    <p class="mt-2 text-sm text-zinc-600">
                        يمكنكم أيضاً التواصل معنا لطلبات خاصة أو الاستفسار عن التوصيل.
                    </p>
                </div>
            </div>
        </div>

        @if($relatedProducts->isNotEmpty())
            <section id="related-products" class="mt-20 scroll-mt-8 border-t border-zinc-200 pt-14" aria-labelledby="related-products-heading">
                <h2 id="related-products-heading" class="text-xl font-bold text-zinc-900">منتجات ذات صلة</h2>
                <p class="mt-1 text-sm text-zinc-500">من نفس التصنيف أو اقتراحات أخرى</p>
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
                    @foreach($relatedProducts as $p)
                        <x-store.product-card :product="$p" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
