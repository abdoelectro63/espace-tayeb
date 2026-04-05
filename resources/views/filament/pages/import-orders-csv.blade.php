<x-filament-panels::page>
    <div class="col-span-full w-full min-w-0 max-w-none space-y-6" dir="rtl">
        @if ($step === 1)
            <x-filament::section>
                <x-slot name="heading">الخطوة 1 — رفع ملف CSV</x-slot>
                <x-slot name="description">
                    يدعم الملفات ذات العناوين ثنائية اللغة (مثل Nom - الاسم، Téléphone - الهاتف، Form Name (ID)، Choisissez la taille) بالإضافة إلى الأعمدة الإنجليزية: customer_name، phone، city، address، product_sku، variation، quantity، …
                </x-slot>

                <form wire:submit="parseCsv" class="space-y-4">
                    <div>
                        <input
                            type="file"
                            wire:model="csv_file"
                            accept=".csv,text/csv"
                            class="fi-input block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500 dark:text-gray-300"
                        />
                        @error('csv_file')
                            <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                        <div wire:loading wire:target="csv_file" class="mt-2 text-xs text-gray-500">جاري تحميل الملف…</div>
                    </div>

                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="parseCsv">
                        متابعة — تحليل الملف
                    </x-filament::button>
                </form>
            </x-filament::section>
        @else
            @php
                $syncProd = $syncProductId ? $this->productsForSelect->firstWhere('id', (int) $syncProductId) : null;
                $variationOptions = $syncProd ? $this->variationOptionsForProduct($syncProd) : [];
            @endphp

            <form wire:submit.prevent="finalizeImport" class="space-y-6">
                @if ($errors->any())
                    <x-filament::section>
                        <x-slot name="heading">تحقق من الحقول</x-slot>
                        <ul class="list-inside list-disc space-y-1 text-sm text-danger-600 dark:text-danger-400">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                @endif

                <x-filament::section :contained="false" class="col-span-full w-full min-w-0 max-w-none [&_.fi-section-content]:!w-full [&_.fi-section-content-ctn]:!w-full">
                    <x-slot name="heading">الخطوة 2 — مطابقة المنتجات والمتغيرات</x-slot>
                    <x-slot name="description">
                        الصفوف بلا <strong>اسم</strong> ولا <strong>مدينة</strong> في الملف تُستبعد تلقائياً. إن وُجدت مدينة دون اسم يُعرَض <strong>Client</strong>؛ إن وُجدت مدينة دون عنوان يُنسَخ العنوان من المدينة. اختر المنتج الموحّد (قائمة Filament مع بحث) لتعيين النوع لكل الصفوف.
                    </x-slot>

                    <div class="flex w-full min-w-0 flex-col gap-8">
                    <div class="w-full rounded-xl border border-amber-200/80 bg-amber-50/90 p-4 dark:border-amber-500/30 dark:bg-amber-950/40">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">المنتج الموحّد لجميع الطلبات</p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            يُطبَّق على كل الصفوف؛ عند التغيير يُحدَّث النوع الافتراضي لكل الصفوف.
                        </p>
                        <div
                            class="import-product-form-ctn mt-3 w-full max-w-none min-w-0 [&_.fi-grid-col]:!max-w-none [&_.fi-sc]:w-full [&_.fi-fo-field]:w-full [&_.fi-fo-field-content-col]:min-w-0 [&_.fi-fo-field-content-col]:max-w-full [&_.fi-fo-select-wrp]:w-full [&_.fi-input-wrp]:w-full [&_.fi-fo-select]:w-full"
                        >
                            {!! $this->getSchema('importProductForm')?->toHtml() ?? '' !!}
                        </div>
                        @error('syncProductId')
                            <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="fi-ta-ctn fi-ta-ctn-with-header mt-10 w-full min-w-0">
                        <div class="fi-ta-main">
                            <div class="fi-ta-header-ctn">
                                <div class="fi-ta-header">
                                    <div>
                                        <h3 class="fi-ta-header-heading">معاينة الطلبات</h3>
                                        <p class="fi-ta-header-description">
                                            راجع الصفوف قبل الاستيراد.
                                            <span class="font-medium text-gray-950 dark:text-white">
                                                {{ count($rows) }}
                                                {{ count($rows) === 1 ? 'صف' : 'صفوف' }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="import-csv-table-scope fi-ta-content-ctn fi-fixed-positioning-context">
                                <div class="fi-ta-content !gap-0">
                                    {{-- No fi-ta-table: Filament applies gray thead/tbody + divide-y on .fi-ta-table --}}
                                    <div class="import-csv-table-wrap w-full overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-950 dark:ring-white/10">
                                    <table
                                        class="import-csv-table import-csv-table-modern w-full min-w-[56rem] !table-fixed text-start"
                                        style="table-layout: fixed; width: 100%"
                                    >
                                        <colgroup>
                                            <col style="width: 16.666%" />
                                            <col style="width: 16.666%" />
                                            <col style="width: 16.666%" />
                                            <col style="width: 16.666%" />
                                            <col style="width: 16.666%" />
                                            <col style="width: 16.67%" />
                                        </colgroup>
                                        <thead>
                                            <tr class="align-top">
                                                <th class="fi-ta-header-cell border-0 border-b border-gray-200 px-5 py-4 text-center text-xs font-bold tracking-wide !text-gray-500 dark:border-white/10 dark:!text-gray-400 sm:first-of-type:ps-8">#</th>
                                                <th class="fi-ta-header-cell border-0 border-b border-gray-200 px-5 py-4 text-start text-xs font-bold tracking-wide !text-gray-500 dark:border-white/10 dark:!text-gray-400">
                                                    الاسم <span class="text-danger-600">*</span>
                                                </th>
                                                <th class="fi-ta-header-cell border-0 border-b border-gray-200 px-5 py-4 text-start text-xs font-bold tracking-wide !text-gray-500 dark:border-white/10 dark:!text-gray-400 whitespace-nowrap">
                                                    الهاتف <span class="text-danger-600">*</span>
                                                </th>
                                                <th class="fi-ta-header-cell border-0 border-b border-gray-200 px-5 py-4 text-start text-xs font-bold tracking-wide !text-gray-500 dark:border-white/10 dark:!text-gray-400">
                                                    المدينة <span class="text-danger-600">*</span>
                                                </th>
                                                <th class="fi-ta-header-cell border-0 border-b border-gray-200 px-5 py-4 text-start text-xs font-bold tracking-wide !text-gray-500 dark:border-white/10 dark:!text-gray-400">
                                                    العنوان <span class="text-danger-600">*</span>
                                                </th>
                                                <th class="fi-ta-header-cell border-0 border-b border-gray-200 px-5 py-4 text-start text-xs font-bold tracking-wide !text-gray-500 dark:border-white/10 dark:!text-gray-400 sm:last-of-type:pe-8">
                                                    <span class="block leading-snug">
                                                        مرجع CSV والنوع
                                                        @if ($syncProd && $syncProd->variations->isNotEmpty())
                                                            <span class="text-danger-600">*</span>
                                                        @endif
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rows as $index => $row)
                                                @php
                                                    $csvRefFull = trim(collect([$row['_raw_sku'] ?? '', $row['_raw_variation'] ?? ''])->filter()->implode(' · '));
                                                @endphp
                                                <tr
                                                    wire:key="import-row-{{ $index }}"
                                                    class="import-csv-tr align-top transition-colors hover:bg-gray-50/70 dark:hover:bg-white/[0.03]"
                                                >
                                                    <td class="fi-ta-cell min-w-0 max-w-full border-0 border-b border-gray-100 px-5 py-5 text-center text-sm tabular-nums text-gray-400 align-top dark:border-white/5 sm:first-of-type:ps-8">
                                                        {{ $index + 1 }}
                                                    </td>
                                                    <td class="fi-ta-cell min-w-0 max-w-full border-0 border-b border-gray-100 px-5 py-5 align-top dark:border-white/5">
                                                        <input
                                                            type="text"
                                                            wire:model.blur="rows.{{ $index }}.customer_name"
                                                            class="fi-input box-border h-9 w-full min-w-0 max-w-full rounded-md border bg-white px-2 py-1 text-sm font-medium leading-none text-gray-950 dark:bg-white/5 dark:text-white @error('rows.'.$index.'.customer_name') border-danger-500 ring-1 ring-danger-500 @else border-gray-200 dark:border-white/10 @enderror"
                                                            autocomplete="name"
                                                        />
                                                        @error('rows.'.$index.'.customer_name')
                                                            <p class="mt-0.5 line-clamp-2 text-[10px] text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                        @enderror
                                                    </td>
                                                    <td class="fi-ta-cell min-w-0 max-w-full border-0 border-b border-gray-100 px-5 py-5 align-top dark:border-white/5">
                                                        <input
                                                            type="text"
                                                            dir="ltr"
                                                            wire:model.blur="rows.{{ $index }}.customer_phone"
                                                            class="fi-input box-border h-9 w-full min-w-0 max-w-full rounded-md border bg-white px-2 py-1 font-mono text-sm leading-none text-gray-600 dark:bg-white/5 dark:text-gray-300 @error('rows.'.$index.'.customer_phone') border-danger-500 ring-1 ring-danger-500 @else border-gray-200 dark:border-white/10 @enderror"
                                                            autocomplete="tel"
                                                        />
                                                        @error('rows.'.$index.'.customer_phone')
                                                            <p class="mt-0.5 line-clamp-2 text-[10px] text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                        @enderror
                                                    </td>
                                                    <td class="fi-ta-cell min-w-0 max-w-full border-0 border-b border-gray-100 px-5 py-5 align-top dark:border-white/5">
                                                        <input
                                                            type="text"
                                                            wire:model.blur="rows.{{ $index }}.city"
                                                            class="fi-input box-border h-9 w-full min-w-0 max-w-full rounded-md border bg-white px-2 py-1 text-sm leading-none text-gray-600 dark:bg-white/5 dark:text-gray-300 @error('rows.'.$index.'.city') border-danger-500 ring-1 ring-danger-500 @else border-gray-200 dark:border-white/10 @enderror"
                                                        />
                                                        @error('rows.'.$index.'.city')
                                                            <p class="mt-0.5 line-clamp-2 text-[10px] text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                        @enderror
                                                    </td>
                                                    <td class="fi-ta-cell min-w-0 max-w-full border-0 border-b border-gray-100 px-5 py-5 align-top dark:border-white/5">
                                                        <textarea
                                                            wire:model.blur="rows.{{ $index }}.shipping_address"
                                                            rows="2"
                                                            style="resize: none"
                                                            class="fi-input import-csv-address-field box-border max-h-[5rem] min-h-[2.25rem] w-full min-w-0 max-w-full resize-none overflow-y-auto rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm leading-snug text-gray-600 break-words dark:border-white/10 dark:bg-white/5 dark:text-gray-300 @error('rows.'.$index.'.shipping_address') border-danger-500 ring-1 ring-danger-500 @enderror"
                                                        ></textarea>
                                                        @error('rows.'.$index.'.shipping_address')
                                                            <p class="mt-0.5 line-clamp-2 text-[10px] text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                        @enderror
                                                    </td>
                                                    <td class="fi-ta-cell min-w-0 max-w-full border-0 border-b border-gray-100 px-5 py-5 align-top dark:border-white/5 sm:last-of-type:pe-8">
                                                        <div class="flex w-full min-w-0 max-w-full flex-col gap-2.5">
                                                            <p
                                                                class="break-words text-start text-sm leading-relaxed text-gray-600 dark:text-gray-300"
                                                                @if ($csvRefFull !== '') title="{{ $csvRefFull }}" @endif
                                                            >
                                                                {{ $csvRefFull !== '' ? $csvRefFull : '—' }}
                                                            </p>
                                                            @if ($syncProd && $syncProd->variations->isNotEmpty())
                                                                <x-csv-import.searchable-select
                                                                    wire:key="import-var-{{ $index }}-{{ $syncProductId }}"
                                                                    compact
                                                                    :options="$variationOptions"
                                                                    placeholder="—"
                                                                    search-placeholder="بحث…"
                                                                    :entangle-key="'rows.'.$index.'.product_variation_id'"
                                                                />
                                                                @error('rows.'.$index.'.product_variation_id')
                                                                    <p class="mt-1 line-clamp-2 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                                @enderror
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <x-filament::button
                            type="submit"
                            color="primary"
                            icon="heroicon-o-check"
                            wire:loading.attr="disabled"
                            wire:target="finalizeImport"
                        >
                            <span wire:loading.remove wire:target="finalizeImport">إنهاء الاستيراد</span>
                            <span wire:loading wire:target="finalizeImport">جاري الاستيراد…</span>
                        </x-filament::button>
                        <x-filament::button type="button" wire:click="resetImport" color="gray" outlined>
                            إلغاء والبدء من جديد
                        </x-filament::button>
                    </div>
                </x-filament::section>
            </form>
        @endif
    </div>
</x-filament-panels::page>
