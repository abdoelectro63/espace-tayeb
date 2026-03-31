<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class DeliveryMonthlyFeesChart extends ChartWidget
{
    protected ?string $heading = 'إحصائيات التوصيل الشهرية';

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

    protected function getData(): array
    {
        $from = now()->startOfMonth();
        $until = now()->endOfMonth();
        $daysInMonth = $from->daysInMonth;

        $labels = [];
        $dailyFees = array_fill(1, $daysInMonth, 0.0);
        $dailyNet = array_fill(1, $daysInMonth, 0.0);

        $rows = $this->getBaseQuery()
            ->selectRaw('DATE(updated_at) as day, SUM(delivery_fee) as fees_sum, SUM(total_price - delivery_fee) as net_sum')
            ->whereBetween('updated_at', [$from, $until])
            ->groupBy('day')
            ->get();

        foreach ($rows as $row) {
            $day = (int) date('j', strtotime((string) $row->day));
            $dailyFees[$day] = (float) ($row->fees_sum ?? 0);
            $dailyNet[$day] = (float) ($row->net_sum ?? 0);
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }

        $datasets = [
            [
                'label' => 'تكاليف التوصيل',
                'data' => array_values($dailyFees),
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
            ],
        ];

        if (! $this->isDeliveryMan()) {
            $datasets[] = [
                'label' => 'المبلغ الواجب تسليمه',
                'data' => array_values($dailyNet),
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
