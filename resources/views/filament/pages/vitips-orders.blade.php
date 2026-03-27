<x-filament-panels::page>
    <div class="space-y-4" dir="rtl">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">تتبع طلبيات شركة توصيل</h2>
                <p class="mt-1 text-xs text-gray-500">إجمالي الطلبيات: {{ number_format($total) }}</p>
            </div>

            <x-filament::button color="gray" wire:click="refreshOrders">
                تحديث الطلبيات
            </x-filament::button>
        </div>

        @if($errorMessage)
            <x-filament::section>
                <p class="text-sm text-danger-600">{{ $errorMessage }}</p>
            </x-filament::section>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">رقم التتبع</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">اسم الزبون</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">حالة الطلبية</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">المدينة</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">المبلغ الإجمالي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-800">{{ $order['tracking_number'] }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ $order['customer_name'] }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    style="background-color: {{ $order['status_badge_bg'] }}; color: {{ $order['status_badge_text'] }};"
                                >
                                    {{ $order['status_label'] }}
                                </span>
                                @if(filled($order['status_note']))
                                    <p class="mt-1 text-xs text-gray-500">
                                        التاريخ: {{ $order['status_note'] }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $order['city'] }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ $order['total_amount'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                لا توجد طلبيات متاحة حاليا.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($lastPage > 1)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3">
                <x-filament::button
                    size="sm"
                    color="gray"
                    :disabled="$currentPage <= 1"
                    wire:click="goToPage({{ $currentPage - 1 }})"
                >
                    السابق
                </x-filament::button>

                <p class="text-sm text-gray-600">
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
