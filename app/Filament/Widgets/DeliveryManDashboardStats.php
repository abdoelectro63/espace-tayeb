<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeliveryManDashboardStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $from = now()->startOfMonth();
        $until = now()->endOfMonth();

        $base = Order::query()
            ->where('status', 'delivered')
            ->where('delivery_man_id', auth()->id())
            ->whereBetween('updated_at', [$from, $until]);

        $benefit = (float) (clone $base)->sum('delivery_fee');
        $unpaidDeliveredAmount = (float) Order::query()
            ->where('status', 'delivered')
            ->where('payment_status', 'unpaid')
            ->where('delivery_man_id', auth()->id())
            ->sum('total_price');

        return [
            Stat::make('Benefit الخاص بك (هذا الشهر)', number_format($benefit, 2).' MAD')
                ->description('مجموع تكاليف التوصيل لهذا الشهر')
                ->icon('heroicon-o-currency-dollar'),
            Stat::make('إجمالي المسلّم غير المدفوع', number_format($unpaidDeliveredAmount, 2).' MAD')
                ->description('مجموع الطلبيات المسلمة وغير المدفوعة')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
