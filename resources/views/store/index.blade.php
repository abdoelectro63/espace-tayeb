<x-layouts.store>
    <section class="relative overflow-hidden border-b border-emerald-900/10 bg-gradient-to-bl from-emerald-800 via-emerald-900 to-zinc-900 text-white">
        <div class="pointer-events-none absolute -left-32 top-0 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-teal-400/15 blur-3xl"></div>
        <div class="relative mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <p class="text-sm font-medium text-emerald-100/90">متجر إلكتروني موثوق</p>
            <h1 class="mt-3 max-w-2xl text-3xl font-bold leading-tight tracking-tight sm:text-4xl md:text-5xl">
                أجهزة منزلية ومنتجات مختارة لبيت عصري ومريح
            </h1>
            <p class="mt-5 max-w-xl text-base leading-relaxed text-emerald-50/90 sm:text-lg">
                تصفح التصنيفات، قارن الأسعار، واطلب بسهولة — واجهة بسيطة وأسعار واضحة بالدرهم.
            </p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="#products" class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 text-sm font-semibold text-emerald-900 shadow-lg transition hover:bg-emerald-50">
                    تصفح المنتجات
                </a>
                <a href="#categories" class="inline-flex items-center justify-center rounded-full border border-white/30 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20">
                    التصنيفات
                </a>
            </div>
        </div>
    </section>

    @if($categories->isNotEmpty())
        <section id="categories" class="mx-auto max-w-6xl scroll-mt-24 px-4 py-14 sm:px-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-zinc-900">التصنيفات</h2>
                    <p class="mt-1 text-sm text-zinc-600">اختر القسم الذي يناسبك</p>
                </div>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($categories as $category)
                    <a
                        href="{{ route('store.category', $category->slug) }}"
                        class="flex items-center justify-between rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-md"
                    >
                        <span class="font-semibold text-zinc-900">{{ $category->name }}</span>
                        <span class="text-emerald-700" aria-hidden="true">←</span>
                    </a>
                @endforeach
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
