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
        'url' => route('product.show', $product->seoRouteParams()),
    ];
@endphp

<x-layouts.store
    :title="$product->seoTitle()"
    :metaDescription="$product->seoDescription()"
    :canonical="route('product.show', $product->seoRouteParams())"
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

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <div class="grid gap-10 lg:grid-cols-2 lg:gap-14">
            <div class="space-y-4">
                <div class="overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm">
                    <img
                        data-main-product-img
                        src="{{ $product->mainImageUrl() }}"
                        alt="{{ $product->name }}"
                        class="aspect-square w-full object-cover"
                    >
                </div>
                @php
                    $gallery = $product->galleryImageUrls();
                @endphp
                @if(count($gallery) > 0)
                    <div class="grid grid-cols-4 gap-2 sm:gap-3">
                        @foreach($gallery as $url)
                            <button type="button" class="overflow-hidden rounded-lg border border-zinc-100 bg-zinc-50" onclick="document.querySelector('[data-main-product-img]').src='{{ $url }}'">
                                <img src="{{ $url }}" alt="" class="aspect-square w-full object-cover" loading="lazy">
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

                <div class="mt-6 flex flex-wrap items-baseline gap-3">
                    <span class="text-3xl font-bold text-zinc-900">{{ number_format($product->effectivePrice(), 2) }} <span class="text-lg font-semibold text-zinc-500">MAD</span></span>
                    @if($product->isOnSale())
                        <span class="text-xl text-zinc-400 line-through">{{ number_format((float) $product->price, 2) }}</span>
                        <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">تخفيض</span>
                    @endif
                </div>

                <div class="mt-6 flex flex-wrap gap-3 text-sm">
                    @if($product->free_shipping)
                        <span class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1.5 font-medium text-orange-700 ring-1 ring-orange-200/80">
                            التوصيل مجاني لهذا المنتج
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
                    <form
                        method="post"
                        action="{{ route('store.cart.add') }}"
                        class="mt-10 flex flex-col gap-4 rounded-2xl border border-orange-100 bg-orange-50/50 p-6 sm:flex-row sm:items-end sm:justify-between"
                        data-add-to-cart
                        data-fly-image="{{ e($product->mainImageUrl()) }}"
                        data-fly-source-selector="[data-main-product-img]"
                    >
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
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
                        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-[#ff751f] px-8 py-3 text-sm font-semibold text-white shadow transition hover:bg-orange-600 sm:w-auto">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138M8.25 20.25a.75.75 0 100-1.5.75.75 0 000 1.5zm9 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                            </svg>
                            <span>أضف إلى السلة</span>
                        </button>
                    </form>
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
            <section class="mt-20 border-t border-zinc-200 pt-14">
                <h2 class="text-xl font-bold text-zinc-900">قد يعجبك أيضاً</h2>
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
                    @foreach($relatedProducts as $p)
                        <x-store.product-card :product="$p" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
