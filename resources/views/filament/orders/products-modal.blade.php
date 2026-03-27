<div class="space-y-3">
    @foreach($items as $item)
        <div class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3">
            <img
                src="{{ $item->product?->mainImageUrl() ?? asset('images/placeholder-product.svg') }}"
                alt=""
                class="h-12 w-12 rounded-md object-cover"
            >
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-zinc-900">
                    {{ $item->product?->name ?? '—' }}
                </p>
                <p class="text-xs text-zinc-500">
                    الكمية: {{ $item->quantity }} — السعر: {{ number_format((float) $item->unit_price, 2) }} MAD
                </p>
            </div>
        </div>
    @endforeach
</div>

