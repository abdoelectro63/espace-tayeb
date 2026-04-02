@props([
    'cartCount' => 0,
    'topMenu' => null,
    'logoUrl' => null,
])

@php
    $branding = \App\Models\ShippingSetting::query()->first();
    $logoUrl = $logoUrl ?? \App\Models\ShippingSetting::storeLogoUrl();

    $headerBgColor = $branding?->header_bg_color ?: '#000000';
    $menuTextColor = $branding?->menu_text_color ?: '#ffffff';
@endphp

<header x-data="{ mobileOpen: false }" class="sticky top-0 z-50" style="background-color: {{ $headerBgColor }};">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="grid h-20 grid-cols-[auto_1fr_auto] items-center gap-3 md:h-24">
            <div class="flex items-center gap-2 sm:gap-3">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-200 lg:hidden"
                    @click="mobileOpen = ! mobileOpen"
                    :aria-expanded="mobileOpen.toString()"
                    aria-label="Open menu"
                    style="color: {{ $menuTextColor }};"
                >
                    <svg x-show="!mobileOpen" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <svg x-show="mobileOpen" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>

                <button
                    type="button"
                    aria-label="Search products"
                    class="hidden h-10 w-10 items-center justify-center rounded-full border border-zinc-200 transition hover:bg-zinc-50 lg:inline-flex"
                    style="color: {{ $menuTextColor }};"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" />
                    </svg>
                </button>
            </div>

            <nav class="hidden items-center justify-center gap-8 text-sm font-semibold lg:flex" style="color: {{ $menuTextColor }};">
                @if (! empty($topMenu?->items) && $topMenu->items->isNotEmpty())
                    @foreach ($topMenu->items as $item)
                        @php($href = $item->resolveUrl())
                        @if (filled($href))
                            <a href="{{ $href }}" class="transition opacity-95 hover:opacity-70">{{ $item->label }}</a>
                        @endif
                    @endforeach
                @else
                    <a href="{{ route('store.home') }}#categories" class="transition opacity-95 hover:opacity-70">Kitchenware</a>
                    <a href="{{ route('store.home') }}#products" class="transition opacity-95 hover:opacity-70">Appliances</a>
                    <a href="{{ route('store.home') }}#products" class="transition opacity-95 hover:opacity-70">Homewares</a>
                    <a href="{{ route('store.contact') }}" class="transition opacity-95 hover:opacity-70">اتصل بنا</a>
                @endif
            </nav>

            <div class="flex items-center justify-end gap-2 sm:gap-3">
                <button
                    type="button"
                    id="store-cart-trigger"
                    data-cart-fly-target
                    data-cart-drawer-url="{{ route('store.cart.drawer', [], false) }}"
                    data-checkout-url="{{ route('store.checkout') }}"
                    data-full-cart-url="{{ route('store.cart') }}"
                    class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-zinc-200 transition hover:bg-zinc-50"
                    aria-expanded="false"
                    aria-controls="cart-drawer-panel"
                    aria-label="Shopping Cart"
                    style="color: {{ $menuTextColor }};"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0016.136-1.804c.131.043.261.089.391.134M15 12a3 3 0 11-6 0 3 3 0 016 0" />
                    </svg>
                    <span
                        id="store-cart-badge"
                        class="absolute -top-1 -end-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#ff751f] px-1 text-[10px] font-bold text-white {{ (int) $cartCount > 0 ? '' : 'hidden' }}"
                        aria-live="polite"
                    >
                        <span id="store-cart-badge-value">{{ (int) $cartCount > 99 ? '99+' : (int) $cartCount }}</span>
                    </span>
                </button>

                <a href="{{ route('store.home') }}" class="shrink-0">
                    <img
                        src="{{ $logoUrl }}"
                        alt="Espace Tayeb - Home Appliances & Kitchenware"
                        class="h-12 w-auto md:h-20"
                        loading="eager"
                    />
                </a>
            </div>
        </div>
    </div>

    <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden" style="background-color: {{ $headerBgColor }};">
        <nav class="mx-auto max-w-6xl space-y-1 px-4 pb-4 text-sm font-semibold sm:px-6" style="color: {{ $menuTextColor }};">
            @if (! empty($topMenu?->items) && $topMenu->items->isNotEmpty())
                @foreach ($topMenu->items as $item)
                    @php($href = $item->resolveUrl())
                    @if (filled($href))
                        <a @click="mobileOpen = false" href="{{ $href }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">{{ $item->label }}</a>
                    @endif
                @endforeach
            @else
                <a @click="mobileOpen = false" href="{{ route('store.home') }}#categories" class="block rounded-lg px-3 py-2 hover:bg-white/10">Kitchenware</a>
                <a @click="mobileOpen = false" href="{{ route('store.home') }}#products" class="block rounded-lg px-3 py-2 hover:bg-white/10">Appliances</a>
                <a @click="mobileOpen = false" href="{{ route('store.home') }}#products" class="block rounded-lg px-3 py-2 hover:bg-white/10">Homewares</a>
                <a @click="mobileOpen = false" href="{{ route('store.contact') }}" class="block rounded-lg px-3 py-2 hover:bg-white/10">اتصل بنا</a>
            @endif
        </nav>
    </div>
</header>
