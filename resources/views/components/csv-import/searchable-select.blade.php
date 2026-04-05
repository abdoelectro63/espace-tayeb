@props([
    'options' => [],
    'placeholder' => '—',
    'searchPlaceholder' => 'بحث…',
    /** @var string Livewire property path, e.g. syncProductId or rows.0.product_variation_id */
    'entangleKey' => '',
])

@php
    $entangleKey = trim($entangleKey);
@endphp

<div
    class="relative"
    x-data="{
        open: false,
        search: '',
        selected: @entangle($entangleKey),
        options: @js($options),
        filtered() {
            const q = (this.search || '').toLowerCase().trim();
            if (! q) {
                return this.options;
            }

            return this.options.filter((o) => String(o.label).toLowerCase().includes(q));
        },
        activeLabel() {
            if (this.selected === null || this.selected === '' || this.selected === undefined) {
                return @js($placeholder);
            }
            const id = String(this.selected);
            const o = this.options.find((x) => String(x.id) === id);

            return o ? o.label : @js($placeholder);
        },
        pick(id) {
            this.selected = id;
            this.open = false;
            this.search = '';
        },
        clearSel() {
            this.selected = null;
            this.open = false;
            this.search = '';
        },
    }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        @click="open = ! open"
        @class([
            'fi-input flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-start text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white',
        ])
    >
        <span class="min-w-0 flex-1 truncate" x-text="activeLabel()"></span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-gray-400" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition
        class="absolute z-30 mt-1 max-h-72 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900"
    >
        <div class="border-b border-gray-100 p-2 dark:border-white/10">
            <input
                type="search"
                x-model="search"
                :placeholder="@js($searchPlaceholder)"
                class="fi-input block w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-white/10 dark:bg-white/5"
                @click.stop
            />
        </div>
        <ul class="max-h-56 overflow-y-auto py-1 text-sm">
            <li>
                <button
                    type="button"
                    class="block w-full px-3 py-2 text-start text-gray-500 hover:bg-gray-50 dark:hover:bg-white/5"
                    @click="clearSel()"
                >
                    {{ $placeholder }}
                </button>
            </li>
            <template x-for="o in filtered()" :key="o.id">
                <li>
                    <button
                        type="button"
                        class="block w-full px-3 py-2 text-start hover:bg-primary-50 dark:hover:bg-white/10"
                        :class="{ 'bg-primary-50 dark:bg-white/10': String(selected) === String(o.id) }"
                        @click="pick(o.id)"
                        x-text="o.label"
                    ></button>
                </li>
            </template>
        </ul>
    </div>
</div>
