<x-layouts.store>
    @php
        $bannerUrl = filled($branding?->hero_banner_path)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($branding->hero_banner_path)
            : null;
        $bannerLink = filled($branding?->hero_banner_link) ? $branding->hero_banner_link : '#products';
    @endphp

    <section class="relative overflow-hidden border-b border-orange-900/10 bg-gradient-to-bl from-emerald-800 via-emerald-900 to-zinc-900 text-white">
        @if($bannerUrl)
            <img src="{{ $bannerUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="eager" fetchpriority="high" decoding="async">
            <div class="absolute inset-0 bg-black/45"></div>
        @endif
        <div class="pointer-events-none absolute -left-32 top-0 h-96 w-96 rounded-full bg-orange-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-orange-400/15 blur-3xl"></div>
        <div class="relative mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <p class="text-sm font-medium text-emerald-100/90">متجر إلكتروني موثوق</p>
            <h1 class="mt-3 max-w-2xl text-3xl font-bold leading-tight tracking-tight sm:text-4xl md:text-5xl">
                أجهزة منزلية ومنتجات مختارة لبيت عصري ومريح
            </h1>
            <p class="mt-5 max-w-xl text-base leading-relaxed text-emerald-50/90 sm:text-lg">
                تصفح التصنيفات، قارن الأسعار، واطلب بسهولة — واجهة بسيطة وأسعار واضحة بالدرهم.
            </p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="{{ $bannerLink }}" class="inline-flex items-center justify-center rounded-full bg-[#ff751f] px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-orange-600">
                    اشتري الآن
                </a>
                <a href="#categories" class="inline-flex items-center justify-center rounded-full border border-white/30 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20">
                    التصنيفات
                </a>
            </div>
        </div>
    </section>

    @if($categories->isNotEmpty())
        <section id="categories" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-14 sm:px-6">
            <h2 class="text-start text-xl font-bold tracking-tight text-zinc-900 sm:text-2xl">
                التصنيفات
            </h2>

            {{-- Bordered panel + 1px grid lines (gap-px + bg line color), like a catalog grid --}}
            <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div
                    class="grid grid-cols-2 gap-px bg-zinc-100 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
                >
                    @foreach($categories as $category)
                        <a
                            href="{{ route('store.category', ['path' => $category->storePath()]) }}"
                            class="group flex min-h-[9.5rem] flex-col items-center justify-center gap-3 bg-white px-3 py-8 text-center transition hover:bg-zinc-50 focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 sm:min-h-[10.5rem] sm:px-4 sm:py-10"
                        >
                            @if($img = $category->imageUrl())
                                <span class="flex h-16 w-full max-w-[5.5rem] items-center justify-center sm:h-20 sm:max-w-[6.5rem]">
                                    <img
                                        src="{{ $img }}"
                                        alt=""
                                        class="max-h-full max-w-full object-contain"
                                        loading="lazy"
                                        width="104"
                                        height="80"
                                    />
                                </span>
                            @elseif($category->icon)
                                <span class="flex h-16 w-16 items-center justify-center text-zinc-700 sm:h-20 sm:w-20">
                                    <x-dynamic-component :component="$category->icon" class="h-12 w-12 object-contain sm:h-14 sm:w-14" />
                                </span>
                            @else
                                <span
                                    class="flex h-16 w-16 items-center justify-center rounded-full bg-zinc-100 text-lg font-semibold text-zinc-500 sm:h-20 sm:w-20 sm:text-xl"
                                    aria-hidden="true"
                                >
                                    {{ \Illuminate\Support\Str::substr($category->name, 0, 1) }}
                                </span>
                            @endif
                            <span
                                class="line-clamp-2 max-w-full px-1 text-xs font-medium leading-snug text-zinc-800 sm:text-sm"
                            >
                                {{ $category->name }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="products" class="mx-auto max-w-6xl scroll-mt-24 px-4 pb-20 sm:px-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-zinc-900">منتجات مميزة</h2>
                <p class="mt-1 text-sm text-zinc-600">آخر الإضافات إلى المتجر</p>
            </div>
        </div>

        @if($featuredProducts->isEmpty())
            <div class="mt-10 rounded-2xl border border-dashed border-zinc-200 bg-white p-12 text-center">
                <p class="text-zinc-600">لا توجد منتجات معروضة حالياً. أضف منتجاتاً من لوحة التحكم لتظهر هنا.</p>
            </div>
        @else
            <div class="mt-10 grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
                @foreach($featuredProducts as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.store>
