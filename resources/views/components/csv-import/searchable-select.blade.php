@props([
    'options' => [],
    'placeholder' => '—',
    'searchPlaceholder' => 'بحث…',
    /** @var string Livewire property path, e.g. syncProductId or rows.0.product_variation_id */
    'entangleKey' => '',
    'compact' => false,
])

@php
    $entangleKey = trim($entangleKey);
@endphp

<div
    @class([
        'csv-import-searchable-select w-full min-w-0 max-w-full',
        'csv-import-select-compact' => $compact,
    ])
    x-data="{
        open: false,
        search: '',
        panelTop: 0,
        panelInlineStart: 0,
        panelWidth: 280,
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
        syncPanel() {
            if (! this.open) {
                return;
            }
            const t = this.$refs.trigger;
            if (! t) {
                return;
            }
            const r = t.getBoundingClientRect();
            this.panelTop = r.bottom + 4;
            this.panelInlineStart = r.left;
            this.panelWidth = Math.max(r.width, 200);
        },
        toggle() {
            this.open = ! this.open;
            if (this.open) {
                this.$nextTick(() => {
                    this.syncPanel();
                    requestAnimationFrame(() => this.syncPanel());
                });
            } else {
                this.search = '';
            }
        },
        windowClick(e) {
            if (! this.open) {
                return;
            }
            if (this.$refs.trigger?.contains(e.target)) {
                return;
            }
            if (this.$refs.panel?.contains(e.target)) {
                return;
            }
            this.open = false;
            this.search = '';
        },
    }"
    x-on:click.window="windowClick($event)"
    x-on:keydown.escape.window="open && (open = false, search = '')"
    x-on:resize.window="syncPanel()"
    x-on:scroll.window="syncPanel()"
>
    <x-filament::input.wrapper class="fi-fo-select w-full" :valid="true">
        <div class="fi-select-input w-full min-w-0">
            <div class="fi-select-input-ctn relative w-full min-w-0">
                <button
                    type="button"
                    x-ref="trigger"
                    x-on:click.stop="toggle()"
                    @class([
                        'fi-select-input-btn w-full max-w-full min-w-0 border-0 bg-transparent shadow-none ring-0 focus:ring-0',
                        '!min-h-8 !py-1 !ps-2.5 !pe-7 !text-xs !leading-5' => $compact,
                    ])"
                >
                    <span class="fi-select-input-value-ctn min-w-0">
                        <span
                            class="fi-select-input-value-label truncate text-start"
                            x-text="activeLabel()"
                        ></span>
                    </span>
                </button>
            </div>
        </div>
    </x-filament::input.wrapper>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-ref="panel"
            class="fi-dropdown-panel fi-width-md max-h-72 overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            x-bind:style="'position:fixed;top:' + panelTop + 'px;left:' + panelInlineStart + 'px;width:' + panelWidth + 'px;max-width:min(100vw - 1rem, 24rem);z-index:9999;margin:0'"
            x-on:click="e => e.stopPropagation()"
        >
            <div class="fi-select-input-search-ctn border-b border-gray-100 p-2 dark:border-white/10">
                <input
                    type="search"
                    x-model="search"
                    :placeholder="@js($searchPlaceholder)"
                    class="fi-input block w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-sm text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    x-on:click="$event.stopPropagation()"
                />
            </div>
            <ul class="fi-select-input-options-ctn max-h-56 overflow-y-auto py-1 text-sm">
                <li>
                    <button
                        type="button"
                        class="fi-select-input-option block w-full px-3 py-2 text-start text-gray-500 hover:bg-gray-50 dark:hover:bg-white/5"
                        x-on:click="clearSel()"
                    >
                        {{ $placeholder }}
                    </button>
                </li>
                <template x-for="o in filtered()" :key="o.id">
                    <li>
                        <button
                            type="button"
                            class="fi-select-input-option block w-full whitespace-normal break-words px-3 py-2 text-start text-gray-950 hover:bg-gray-50 dark:text-white dark:hover:bg-white/10"
                            :class="{ 'bg-gray-50 dark:bg-white/10': String(selected) === String(o.id) }"
                            x-on:click="pick(o.id)"
                            x-text="o.label"
                        ></button>
                    </li>
                </template>
            </ul>
        </div>
    </template>
</div>
