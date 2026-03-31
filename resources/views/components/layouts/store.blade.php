@props([
    'title' => null,
    'metaDescription' => null,
    'canonical' => null,
    'breadcrumbItems' => [],
])

@php
    $pageTitle = $title ? $title.' — '.config('app.name') : config('app.name');
    $canonicalUrl = $canonical ?? url()->current();
    $breadcrumbList = collect($breadcrumbItems ?? [])
        ->filter(fn ($item) => filled($item['name'] ?? null) && filled($item['url'] ?? null))
        ->values();
    $breadcrumbSchema = $breadcrumbList->isNotEmpty()
        ? [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbList->values()->map(
                fn ($item, $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ]
            )->all(),
        ]
        : null;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? 'متجر إلكتروني — أجهزة منزلية ومنتجات مختارة بعناية.' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @if($breadcrumbSchema)
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    <x-layout.header :cart-count="$cartCount ?? 0" :top-menu="$topMenu ?? null" />

    @if(session('cart_success'))
        <div class="border-b border-orange-100 bg-orange-50 px-4 py-2 text-center text-sm font-medium text-orange-800">
            {{ session('cart_success') }}
        </div>
    @endif

    <div
        id="cart-toast"
        class="pointer-events-none fixed top-20 left-1/2 z-[10000] hidden -translate-x-1/2 translate-y-2 rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white opacity-0 shadow-lg transition-all duration-300 sm:top-24"
        role="status"
        aria-live="polite"
    ></div>

    <main>
        {{ $slot }}
    </main>

    <footer class="store-footer mt-20">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-1">
                    @if (! empty($footerLogoUrl))
                        <img src="{{ $footerLogoUrl }}" alt="" class="mb-4 h-10 w-auto object-contain" loading="lazy" />
                    @endif
                    <p class="store-footer-title text-lg font-bold">{{ config('app.name') }}</p>
                    <p class="mt-2 max-w-sm text-sm leading-relaxed">
                        {{ filled(trim($footerSettings->tagline ?? '')) ? $footerSettings->tagline : 'متجركم للأجهزة المنزلية والمنتجات المختارة — جودة، شفافية في الأسعار، وخدمة قريبة منكم.' }}
                    </p>
                </div>
                @php
                    $footerCols = [
                        ['menu' => $footerMenu1 ?? null, 'fallbackTitle' => 'روابط'],
                        ['menu' => $footerMenu2 ?? null, 'fallbackTitle' => 'عمود 2'],
                        ['menu' => $footerMenu3 ?? null, 'fallbackTitle' => 'عمود 3'],
                    ];
                @endphp
                @foreach ($footerCols as $col)
                    @php
                        $menu = $col['menu'];
                    @endphp
                    @if (! empty($menu?->items) && $menu->items->isNotEmpty())
                        <div class="text-sm">
                            <p class="store-footer-title font-semibold">{{ $menu->name ?: $col['fallbackTitle'] }}</p>
                            <ul class="mt-3 space-y-2">
                                @foreach ($menu->items as $item)
                                    @php
                                        $href = $item->resolveUrl();
                                    @endphp
                                    @if (filled($href))
                                        <li><a href="{{ $href }}" class="underline-offset-4 hover:underline">{{ $item->label }}</a></li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
                @php
                    $hasFooterCms = collect($footerCols)->contains(fn ($c) => ! empty($c['menu']?->items) && $c['menu']->items->isNotEmpty());
                @endphp
                @unless ($hasFooterCms)
                    <div class="text-sm">
                        <p class="store-footer-title font-semibold">روابط</p>
                        <ul class="mt-3 space-y-2">
                            <li><a href="{{ route('store.home') }}" class="underline-offset-4 hover:underline">الرئيسية</a></li>
                            <li><a href="{{ route('store.home') }}#products" class="underline-offset-4 hover:underline">المنتجات</a></li>
                            <li><a href="{{ route('store.cart') }}" class="underline-offset-4 hover:underline">سلة التسوق</a></li>
                            <li><a href="{{ route('store.checkout') }}" class="underline-offset-4 hover:underline">إتمام الشراء</a></li>
                            <li><a href="{{ route('page.show', ['slug' => 'privacy-policy']) }}" class="underline-offset-4 hover:underline">سياسة الخصوصية</a></li>
                        </ul>
                    </div>
                @endunless
            </div>
            @if (! empty($footerSettings?->social_links))
                <div class="mt-8 flex flex-wrap justify-center gap-4 text-sm">
                    @foreach ($footerSettings->social_links as $link)
                        @if (filled($link['url'] ?? null))
                            @php
                                $socialIconKey = \App\Support\FooterSocialIcons::normalizeKey($link['icon'] ?? null);
                            @endphp
                            <a href="{{ $link['url'] }}" class="inline-flex items-center gap-2 underline-offset-4 hover:underline" rel="noopener noreferrer" target="_blank">
                                <x-store.social-icon :name="$socialIconKey" class="h-5 w-5 shrink-0 opacity-90" />
                                <span>{{ $link['platform'] ?? __('Link') }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
            <p class="store-footer-divider mt-10 border-t pt-8 text-center text-xs">
                @if (filled($footerSettings?->copyright_text))
                    {{ str_replace('{year}', (string) date('Y'), $footerSettings->copyright_text) }}
                @else
                    &copy; {{ date('Y') }} . جميع الحقوق محفوظة.
                @endif
            </p>
        </div>
    </footer>

    {{-- Mini-cart last in DOM + high z-index so it stays above <main> for hit-testing --}}
    <div
        id="cart-drawer-root"
        class="fixed inset-0 z-[200] invisible"
        style="pointer-events: none"
        aria-hidden="true"
        data-open="0"
    >
        <div
            id="cart-drawer-backdrop"
            class="absolute inset-0 z-0 cursor-pointer bg-zinc-900/50 opacity-0 transition-opacity duration-300"
            style="pointer-events: none"
            aria-hidden="true"
        ></div>
        <aside
            id="cart-drawer-panel"
            class="absolute inset-y-0 left-0 z-10 flex h-full w-full max-w-md -translate-x-full transform flex-col bg-white shadow-2xl transition-transform duration-300 ease-out"
            style="pointer-events: none"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cart-drawer-title"
        >
            <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-4">
                <h2 id="cart-drawer-title" class="text-lg font-bold text-zinc-900">سلة التسوق</h2>
                <button
                    type="button"
                    id="cart-drawer-close"
                    class="rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900"
                    aria-label="إغلاق"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div id="cart-drawer-items" class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                <p class="py-10 text-center text-sm text-zinc-500">اضغط على السلة لعرض المنتجات.</p>
            </div>
            <div class="border-t border-zinc-100 bg-zinc-50/80 px-4 py-4">
                <p class="mb-3 text-xs leading-relaxed text-zinc-500">التوصيل متاح داخل المغرب فقط. اختر المدينة من صفحة السلة أو عند الدفع.</p>
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-zinc-600">المجموع الفرعي</span>
                    <span id="cart-drawer-subtotal" class="font-semibold text-zinc-900">0.00 MAD</span>
                </div>
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-zinc-600">التوصيل</span>
                    <span id="cart-drawer-shipping" class="font-semibold text-zinc-900">—</span>
                </div>
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-zinc-600">الإجمالي</span>
                    <span id="cart-drawer-grand-total" class="text-lg font-bold text-zinc-900">—</span>
                </div>
                <p id="cart-drawer-shipping-hint" class="mb-3 hidden text-xs text-zinc-500"></p>
                <div class="flex flex-col gap-3 sm:flex-row-reverse">
                    <a
                        id="cart-drawer-checkout"
                        href="{{ route('store.checkout') }}"
                        class="inline-flex flex-1 items-center justify-center rounded-full bg-[#ff751f] px-4 py-3 text-sm font-semibold text-white shadow transition hover:bg-orange-600"
                    >
                        إتمام الشراء
                    </a>
                    <button
                        type="button"
                        id="cart-drawer-continue"
                        class="inline-flex flex-1 items-center justify-center rounded-full border border-zinc-200 bg-white px-4 py-3 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50"
                    >
                        متابعة التسوق
                    </button>
                </div>
                <a href="{{ route('store.cart') }}" class="mt-4 block text-center text-sm font-medium text-orange-600 hover:underline">
                    عرض السلة كاملة
                </a>
            </div>
        </aside>
    </div>
</body>
</html>
