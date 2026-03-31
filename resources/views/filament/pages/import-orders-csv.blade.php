<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        @if($step === 1)
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
                            class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500"
                        />
                        @error('csv_file')
                            <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                        <div wire:loading wire:target="csv_file" class="mt-2 text-xs text-gray-500">جاري تحميل الملف…</div>
                    </div>

                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        متابعة — تحليل الملف
                    </x-filament::button>
                </form>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">الخطوة 2 — مطابقة المنتجات والمتغيرات</x-slot>
                <x-slot name="description">عدّل بيانات كل طلب في الجدول؛ الحقول الفارغة الإلزامية يجب تعبئتها قبل الإنهاء. اختر المنتج <strong>مرة واحدة</strong> لجميع الطلبات، ثم اضبط <strong>النوع</strong> لكل صف إن لزم.</x-slot>

                <div class="mb-4 rounded-lg border border-amber-100 bg-amber-50/80 p-4">
                    <label for="import-sync-product" class="block text-sm font-semibold text-gray-800">المنتج الموحّد لجميع الطلبات</label>
                    <p class="mt-1 text-xs text-gray-600">يُطبَّق على كل الصفوف؛ تغيير المنتج يعيد تعيين النوع الافتراضي لكل الصفوف (يمكنك تعديل النوع لكل صف بعد ذلك).</p>
                    <select
                        id="import-sync-product"
                        wire:model.live="syncProductId"
                        class="fi-input mt-2 block w-full max-w-xl rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
                    >
                        <option value="">— اختر المنتج —</option>
                        @foreach($this->productsForSelect as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                    @error('syncProductId')
                        <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                @php
                    $syncProd = $syncProductId ? $this->productsForSelect->firstWhere('id', (int) $syncProductId) : null;
                @endphp

                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-[72rem] w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-2 text-center font-semibold text-gray-600 w-10">#</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-700 min-w-[8rem]">الزبون <span class="text-danger-600">*</span></th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-700 min-w-[7rem]">الهاتف <span class="text-danger-600">*</span></th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-700 min-w-[6rem]">المدينة <span class="text-danger-600">*</span></th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-700 min-w-[10rem]">العنوان <span class="text-danger-600">*</span></th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-600 min-w-[7rem]">SKU / متغير (CSV)</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-700 w-20">الكمية <span class="text-danger-600">*</span></th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-700 min-w-[11rem]">النوع @if($syncProd && $syncProd->variations->isNotEmpty())<span class="text-danger-600">*</span>@endif</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($rows as $index => $row)
                                <tr wire:key="import-row-{{ $index }}" class="align-top">
                                    <td class="px-2 py-2 text-center text-xs text-gray-500">{{ $index + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            wire:model.blur="rows.{{ $index }}.customer_name"
                                            class="fi-input block w-full min-w-[7rem] rounded-lg border bg-white px-2 py-1.5 text-sm @error('rows.'.$index.'.customer_name') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 @enderror"
                                            autocomplete="name"
                                        />
                                        @error('rows.'.$index.'.customer_name')
                                            <p class="mt-0.5 text-xs text-danger-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            dir="ltr"
                                            wire:model.blur="rows.{{ $index }}.customer_phone"
                                            class="fi-input block w-full min-w-[6rem] rounded-lg border bg-white px-2 py-1.5 text-sm @error('rows.'.$index.'.customer_phone') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 @enderror"
                                            autocomplete="tel"
                                        />
                                        @error('rows.'.$index.'.customer_phone')
                                            <p class="mt-0.5 text-xs text-danger-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="text"
                                            wire:model.blur="rows.{{ $index }}.city"
                                            class="fi-input block w-full rounded-lg border bg-white px-2 py-1.5 text-sm @error('rows.'.$index.'.city') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 @enderror"
                                        />
                                        @error('rows.'.$index.'.city')
                                            <p class="mt-0.5 text-xs text-danger-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-3 py-2">
                                        <textarea
                                            wire:model.blur="rows.{{ $index }}.shipping_address"
                                            rows="2"
                                            class="fi-input block w-full rounded-lg border bg-white px-2 py-1.5 text-sm @error('rows.'.$index.'.shipping_address') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 @enderror"
                                        ></textarea>
                                        @error('rows.'.$index.'.shipping_address')
                                            <p class="mt-0.5 text-xs text-danger-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600">
                                        {{ $row['_raw_sku'] ?? '' }}
                                        @if(!empty($row['_raw_variation']))
                                            <br><span class="text-gray-500">{{ $row['_raw_variation'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            type="number"
                                            min="1"
                                            max="999"
                                            wire:model.blur="rows.{{ $index }}.quantity"
                                            class="fi-input block w-full max-w-[5rem] rounded-lg border bg-white px-2 py-1.5 text-sm @error('rows.'.$index.'.quantity') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 @enderror"
                                        />
                                        @error('rows.'.$index.'.quantity')
                                            <p class="mt-0.5 text-xs text-danger-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-3 py-2 min-w-[11rem]">
                                        @if($syncProd && $syncProd->variations->isNotEmpty())
                                            <select
                                                wire:model.live="rows.{{ $index }}.product_variation_id"
                                                class="fi-input block w-full rounded-lg border bg-white px-2 py-1.5 text-sm @error('rows.'.$index.'.product_variation_id') border-danger-500 ring-1 ring-danger-500 @else border-gray-300 @enderror"
                                            >
                                                <option value="">— اختر النوع —</option>
                                                @foreach($syncProd->variations as $v)
                                                    <option value="{{ $v->id }}">{{ $v->label() }} @if(filled($v->sku))({{ $v->sku }})@endif — {{ number_format((float) $v->price, 2) }} MAD</option>
                                                @endforeach
                                            </select>
                                            @error('rows.'.$index.'.product_variation_id')
                                                <p class="mt-0.5 text-xs text-danger-600">{{ $message }}</p>
                                            @enderror
                                        @elseif($syncProd)
                                            <span class="text-xs text-gray-500">بدون متغيرات</span>
                                        @else
                                            <span class="text-xs text-gray-400">اختر المنتج أعلاه</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button wire:click="finalizeImport" color="primary" icon="heroicon-o-check">
                        إنهاء الاستيراد
                    </x-filament::button>
                    <x-filament::button wire:click="resetImport" color="gray" outlined>
                        إلغاء والبدء من جديد
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
