<div class="overflow-x-auto">
    <table class="w-full border-collapse text-sm">
        <thead>
            <tr class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/80">
                <th
                    colspan="2"
                    scope="colgroup"
                    class="px-3 py-2.5 text-start text-sm font-semibold text-zinc-900 dark:text-zinc-100"
                >
                    عدة منتجات
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-700/80">
                    <td class="w-16 align-middle p-3 pe-0">
                        <img
                            src="{{ $item->product?->mainImageUrl() ?? asset('images/placeholder-product.svg') }}"
                            alt=""
                            class="h-12 w-12 rounded-md object-cover"
                        >
                    </td>
                    <td class="min-w-0 align-middle p-3 ps-3">
                        <p class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $item->product?->name ?? '—' }}
                        </p>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            الكمية: {{ $item->quantity }} — السعر: {{ number_format((float) $item->unit_price, 2) }} MAD
                        </p>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
