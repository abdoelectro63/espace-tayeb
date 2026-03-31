<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeliveryMonthlyStatsOverview extends StatsOverviewWidget
{
    protected function isDeliveryMan(): bool
    {
        return auth()->user()?->role === 'delivery_man';
    }

    protected function getBaseQuery()
    {
        $query = Order::query()
            ->where('status', 'delivered')
            ->whereNotNull('delivery_man_id');

        if ($this->isDeliveryMan()) {
            $query->where('delivery_man_id', auth()->id());
        }

        return $query;
    }

    protected function getStats(): array
    {
        $from = now()->startOfMonth();
        $until = now()->endOfMonth();

        $base = $this->getBaseQuery()
            ->whereBetween('updated_at', [$from, $until]);

        $deliveredCount = (clone $base)->count();
        $gross = (float) (clone $base)->sum('total_price');
        $fees = (float) (clone $base)->sum('delivery_fee');
        $net = $gross - $fees;

        if ($this->isDeliveryMan()) {
            $unpaidDeliveredAmount = (float) $this->getBaseQuery()
                ->where('payment_status', 'unpaid')
                ->sum('total_price');

            return [
                Stat::make('Benefit الخاص بك (هذا الشهر)', number_format($fees, 2).' MAD')
                    ->description('مجموع تكاليف التوصيل لهذا الشهر')
                    ->icon('heroicon-o-currency-dollar'),
                Stat::make('إجمالي المسلّم غير المدفوع', number_format($unpaidDeliveredAmount, 2).' MAD')
                    ->description('مجموع الطلبيات المسلمة وغير المدفوعة')
                    ->icon('heroicon-o-banknotes'),
            ];
        }

        return [
            Stat::make('طلبات مسلمة (هذا الشهر)', (string) $deliveredCount)
                ->description('الشهر الحالي')
                ->icon('heroicon-o-truck'),
            Stat::make('تكاليف التوصيل (Benefit)', number_format($fees, 2).' MAD')
                ->description('مجموع عمولة الموزعين')
                ->icon('heroicon-o-currency-dollar'),
            Stat::make('المبلغ الواجب تسليمه', number_format($net, 2).' MAD')
                ->description('المجموع الكلي - تكاليف التوصيل')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
