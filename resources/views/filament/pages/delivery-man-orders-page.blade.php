<x-filament-panels::page>
    <div class="space-y-4" dir="rtl">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">طلبياتي</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        إجمالي الطلبيات: {{ number_format($total) }} | عرض {{ $perPage }} في كل صفحة
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                    <x-heroicon-o-truck class="me-1 h-4 w-4" />
                    Delivery Man
                </span>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <button
                    type="button"
                    wire:click="switchTab('delivered_unpaid')"
                    @class([
                        'flex items-center justify-between rounded-xl px-4 py-3 text-sm font-medium transition-all',
                        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200' => $activeTab === 'delivered_unpaid',
                        'bg-gray-50 text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $activeTab !== 'delivered_unpaid',
                    ])
                >
                    <span class="inline-flex items-center gap-2">
                        <x-heroicon-o-clock class="h-4 w-4" />
                        Unpaid Orders
                    </span>
                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-xs dark:bg-black/20">
                        {{ $deliveredUnpaidCount }}
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="switchTab('paid_completed')"
                    @class([
                        'flex items-center justify-between rounded-xl px-4 py-3 text-sm font-medium transition-all',
                        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200' => $activeTab === 'paid_completed',
                        'bg-gray-50 text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $activeTab !== 'paid_completed',
                    ])
                >
                    <span class="inline-flex items-center gap-2">
                        <x-heroicon-o-check-badge class="h-4 w-4" />
                        Paid Completed Orders
                    </span>
                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-xs dark:bg-black/20">
                        {{ $paidCompletedCount }}
                    </span>
                </button>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($orders as $order)
                <article class="group rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center gap-3 lg:gap-5">
                        <div class="min-w-[170px] rounded-xl bg-gray-50 px-3 py-2 dark:bg-gray-800">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">رقم الطلبية</p>
                            <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $order['number'] }}</p>
                        </div>

                        <div class="min-w-[170px]">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">الزبون</p>
                            <p class="mt-1 flex items-center gap-1 font-medium text-gray-900 dark:text-gray-100">
                                <x-heroicon-o-user class="h-4 w-4 text-gray-500" />
                                {{ $order['customer_name'] }}
                            </p>
                        </div>

                        <div class="min-w-[150px]">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">الهاتف</p>
                            <p class="mt-1 flex items-center gap-1 font-medium text-gray-900 dark:text-gray-100">
                                <x-heroicon-o-phone class="h-4 w-4 text-gray-500" />
                                {{ $order['customer_phone'] }}
                            </p>
                        </div>

                        <div class="min-w-[140px]">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">المدينة</p>
                            <p class="mt-1 flex items-center gap-1 font-medium text-gray-900 dark:text-gray-100">
                                <x-heroicon-o-map-pin class="h-4 w-4 text-gray-500" />
                                {{ $order['city'] }}
                            </p>
                        </div>

                        <div class="min-w-[220px] flex-1">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">العنوان</p>
                            <p class="mt-1 truncate font-medium text-gray-900 dark:text-gray-100" title="{{ $order['shipping_address'] }}">
                                {{ $order['shipping_address'] }}
                            </p>
                        </div>

                        <div class="min-w-[120px]">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">الحالة</p>
                            <div class="mt-1">
                                <x-filament::badge :color="$order['status_color']">
                                    {{ $order['status_label'] }}
                                </x-filament::badge>
                            </div>
                        </div>

                        @if($activeTab === 'paid_completed')
                            <div class="min-w-[120px]">
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">الدفع</p>
                                <div class="mt-1">
                                    <x-filament::badge color="success">
                                        مدفوع
                                    </x-filament::badge>
                                </div>
                            </div>
                        @endif

                        <div class="min-w-[130px]">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">المبلغ</p>
                            <p class="mt-1 flex items-center gap-1 font-semibold text-emerald-700 dark:text-emerald-400">
                                <x-heroicon-o-banknotes class="h-4 w-4" />
                                {{ number_format($order['total_price'], 2) }} MAD
                            </p>
                        </div>

                        @if($activeTab !== 'paid_completed')
                            <div class="min-w-[260px]">
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">تغيير الحالة</p>
                                <div class="mt-1">
                                    <select
                                        wire:model="statusInputs.{{ $order['id'] }}"
                                        wire:change="updateOrderStatus({{ $order['id'] }})"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="delivered">Livre</option>
                                        <option value="cancelled">Annule</option>
                                        <option value="no_response">Pas de reponse</option>
                                        <option value="refuse">Refuse</option>
                                        <option value="reporter">Reporter</option>
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                    لا توجد طلبيات متاحة حاليا.
                </div>
            @endforelse
        </div>

        @if($lastPage > 1)
            <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <x-filament::button
                    size="sm"
                    color="gray"
                    :disabled="$currentPage <= 1"
                    wire:click="goToPage({{ $currentPage - 1 }})"
                >
                    السابق
                </x-filament::button>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    الصفحة {{ $currentPage }} من {{ $lastPage }}
                </p>

                <x-filament::button
                    size="sm"
                    color="gray"
                    :disabled="$currentPage >= $lastPage"
                    wire:click="goToPage({{ $currentPage + 1 }})"
                >
                    التالي
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
