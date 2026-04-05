<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
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

                <x-filament::section>
                    <x-slot name="heading">الخطوة 2 — مطابقة المنتجات والمتغيرات</x-slot>
                    <x-slot name="description">
                        عدّل بيانات كل طلب في الجدول؛ الحقول الفارغة الإلزامية يجب تعبئتها قبل الإنهاء. اختر المنتج <strong>مرة واحدة</strong> لجميع الطلبات (مع بحث)، ثم اضبط <strong>النوع</strong> لكل صف إن لزم.
                    </x-slot>

                    <div class="mb-6 rounded-xl border border-amber-200/80 bg-amber-50/90 p-4 dark:border-amber-500/30 dark:bg-amber-950/40">
                        <label class="block text-sm font-semibold text-gray-900 dark:text-white">المنتج الموحّد لجميع الطلبات</label>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            يُطبَّق على كل الصفوف؛ تغيير المنتج يعيد تعيين النوع الافتراضي لكل الصفوف (يمكنك تعديل النوع لكل صف بعد ذلك).
                        </p>
                        <div class="mt-3 max-w-2xl">
                            <x-csv-import.searchable-select
                                :options="$this->productOptionsForJs"
                                placeholder="— اختر المنتج —"
                                search-placeholder="ابحث بالاسم أو الرمز…"
                                entangle-key="syncProductId"
                            />
                        </div>
                        @error('syncProductId')
                            <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div
                        @class([
                            'fi-ta-ctn overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10',
                        ])
                    >
                        <div class="fi-ta-content relative overflow-x-auto dark:border-white/10">
                            <table
                                class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5"
                            >
                                <thead class="divide-y divide-gray-200 dark:divide-white/5">
                                    <tr class="bg-gray-50 dark:bg-white/5">
                                        <th class="fi-ta-header-cell px-3 py-3 text-center text-sm font-semibold text-gray-950 dark:text-white w-12">#</th>
                                        <th class="fi-ta-header-cell px-3 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white min-w-[8rem]">الزبون <span class="text-danger-600">*</span></th>
                                        <th class="fi-ta-header-cell px-3 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white min-w-[7rem]">الهاتف <span class="text-danger-600">*</span></th>
                                        <th class="fi-ta-header-cell px-3 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white min-w-[6rem]">المدينة <span class="text-danger-600">*</span></th>
                                        <th class="fi-ta-header-cell px-3 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white min-w-[12rem]">العنوان <span class="text-danger-600">*</span></th>
                                        <th class="fi-ta-header-cell px-3 py-3 text-start text-sm font-semibold text-gray-700 dark:text-gray-300 min-w-[8rem]">SKU / متغير (CSV)</th>
                                        <th class="fi-ta-header-cell px-3 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white w-24">الكمية <span class="text-danger-600">*</span></th>
                                        <th class="fi-ta-header-cell px-3 py-3 text-start text-sm font-semibold text-gray-950 dark:text-white min-w-[14rem]">
                                            النوع
                                            @if ($syncProd && $syncProd->variations->isNotEmpty())
                                                <span class="text-danger-600">*</span>
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                    @foreach ($rows as $index => $row)
                                        <tr wire:key="import-row-{{ $index }}" class="fi-ta-row">
                                            <td class="fi-ta-cell px-3 py-2 text-center text-xs text-gray-500 dark:text-gray-400">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="fi-ta-cell px-3 py-2 align-top">
                                                <input
                                                    type="text"
                                                    wire:model.blur="rows.{{ $index }}.customer_name"
                                                    class="fi-input block w-full min-w-[7rem] rounded-lg border bg-white px-2 py-1.5 text-sm dark:bg-white/5 @error('rows.'.$index.'.customer_name') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 dark:border-white/10 @enderror"
                                                    autocomplete="name"
                                                />
                                                @error('rows.'.$index.'.customer_name')
                                                    <p class="mt-0.5 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="fi-ta-cell px-3 py-2 align-top">
                                                <input
                                                    type="text"
                                                    dir="ltr"
                                                    wire:model.blur="rows.{{ $index }}.customer_phone"
                                                    class="fi-input block w-full min-w-[6rem] rounded-lg border bg-white px-2 py-1.5 text-sm dark:bg-white/5 @error('rows.'.$index.'.customer_phone') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 dark:border-white/10 @enderror"
                                                    autocomplete="tel"
                                                />
                                                @error('rows.'.$index.'.customer_phone')
                                                    <p class="mt-0.5 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="fi-ta-cell px-3 py-2 align-top">
                                                <input
                                                    type="text"
                                                    wire:model.blur="rows.{{ $index }}.city"
                                                    class="fi-input block w-full rounded-lg border bg-white px-2 py-1.5 text-sm dark:bg-white/5 @error('rows.'.$index.'.city') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 dark:border-white/10 @enderror"
                                                />
                                                @error('rows.'.$index.'.city')
                                                    <p class="mt-0.5 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="fi-ta-cell px-3 py-2 align-top">
                                                <textarea
                                                    wire:model.blur="rows.{{ $index }}.shipping_address"
                                                    rows="2"
                                                    class="fi-input block w-full rounded-lg border bg-white px-2 py-1.5 text-sm dark:bg-white/5 @error('rows.'.$index.'.shipping_address') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 dark:border-white/10 @enderror"
                                                ></textarea>
                                                @error('rows.'.$index.'.shipping_address')
                                                    <p class="mt-0.5 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="fi-ta-cell px-3 py-2 align-top text-xs text-gray-600 dark:text-gray-400">
                                                {{ $row['_raw_sku'] ?? '' }}
                                                @if (! empty($row['_raw_variation']))
                                                    <br /><span class="text-gray-500 dark:text-gray-500">{{ $row['_raw_variation'] }}</span>
                                                @endif
                                            </td>
                                            <td class="fi-ta-cell px-3 py-2 align-top">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="999"
                                                    wire:model.blur="rows.{{ $index }}.quantity"
                                                    class="fi-input block w-full max-w-[5.5rem] rounded-lg border bg-white px-2 py-1.5 text-sm dark:bg-white/5 @error('rows.'.$index.'.quantity') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 dark:border-white/10 @enderror"
                                                />
                                                @error('rows.'.$index.'.quantity')
                                                    <p class="mt-0.5 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="fi-ta-cell min-w-[14rem] px-3 py-2 align-top">
                                                @if ($syncProd && $syncProd->variations->isNotEmpty())
                                                    <x-csv-import.searchable-select
                                                        :options="$variationOptions"
                                                        placeholder="— اختر النوع —"
                                                        search-placeholder="ابحث عن النوع…"
                                                        :entangle-key="'rows.'.$index.'.product_variation_id'"
                                                    />
                                                    @error('rows.'.$index.'.product_variation_id')
                                                        <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                                    @enderror
                                                @elseif ($syncProd)
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">بدون متغيرات</span>
                                                @else
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">اختر المنتج أعلاه</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
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
