@props(['product'])

<div class="group flex flex-col overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm transition hover:border-orange-200/80 hover:shadow-md">
    <a href="{{ route('product.show', $product->seoRouteParams()) }}" class="flex flex-1 flex-col">
        <div class="relative aspect-square overflow-hidden bg-zinc-100">
            <img
                src="{{ $product->mainImageUrl() }}"
                alt="{{ $product->name }}"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                loading="lazy"
            >
            @if($product->isOnSale())
                <span class="absolute left-3 top-3 rounded-full bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white shadow">
                    عرض
                </span>
            @endif
            @if(! $product->inStock())
                <span class="absolute bottom-3 right-3 rounded-full bg-zinc-900/75 px-2.5 py-1 text-xs font-medium text-white">
                    غير متوفر
                </span>
            @endif
        </div>
        <div class="flex flex-1 flex-col gap-2 p-4">
            @if($product->category)
                <p class="text-xs font-medium text-orange-700/90">{{ $product->category->name }}</p>
            @endif
            <div class="flex flex-wrap items-start gap-2">
                <h3 class="line-clamp-2 flex-1 text-sm font-semibold leading-snug text-zinc-900 group-hover:text-orange-700">{{ $product->name }}</h3>
                @if($product->free_shipping)
                    <span class="shrink-0 rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-700" title="Livraison gratuite">Livraison gratuite</span>
                @endif
            </div>
            <div class="mt-auto flex items-baseline gap-2 pt-1">
                <span class="text-base font-bold text-zinc-900">{{ number_format($product->effectivePrice(), 2) }} <span class="text-xs font-semibold text-zinc-500">MAD</span></span>
                @if($product->isOnSale())
                    <span class="text-sm text-zinc-400 line-through">{{ number_format((float) $product->price, 2) }}</span>
                @endif
            </div>
        </div>
    </a>
    @if($product->inStock())
        <form
            method="post"
            action="{{ route('store.cart.add') }}"
            class="border-t border-zinc-100 p-3"
            data-add-to-cart
            data-fly-image="{{ e($product->mainImageUrl()) }}"
        >
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#ff751f] py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138M8.25 20.25a.75.75 0 100-1.5.75.75 0 000 1.5zm9 0a.75.75 0 100-1.5.75.75 0 000 1.5z" />
                </svg>
                <span>أضف إلى السلة</span>
            </button>
        </form>
    @endif
</div>
