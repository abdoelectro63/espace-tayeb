<x-layouts.store :title="$category->name" :metaDescription="'منتجات قسم '.$category->name">
    <div class="border-b border-zinc-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <nav class="text-xs text-zinc-500">
                <a href="{{ route('store.home') }}" class="hover:text-emerald-800">الرئيسية</a>
                <span class="mx-2">/</span>
                <span class="text-zinc-800">{{ $category->name }}</span>
            </nav>
            <h1 class="mt-4 text-3xl font-bold text-zinc-900">{{ $category->name }}</h1>
            <p class="mt-2 text-sm text-zinc-600">جميع المنتجات المفعّلة في هذا التصنيف</p>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if($products->isEmpty())
            <div class="rounded-2xl border border-dashed border-zinc-200 bg-white p-12 text-center text-zinc-600">
                لا توجد منتجات في هذا التصنيف بعد.
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
                @foreach($products as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>
            <div class="mt-12">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</x-layouts.store>
