<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class DeliveryManDashboardBenefitChart extends ChartWidget
{
    protected ?string $heading = 'Benefit الشهري';

    protected function getData(): array
    {
        $from = now()->startOfMonth();
        $until = now()->endOfMonth();
        $daysInMonth = $from->daysInMonth;

        $labels = [];
        $dailyBenefit = array_fill(1, $daysInMonth, 0.0);

        $rows = Order::query()
            ->selectRaw('DATE(updated_at) as day, SUM(delivery_fee) as benefit_sum')
            ->where('status', 'delivered')
            ->where('delivery_man_id', auth()->id())
            ->whereBetween('updated_at', [$from, $until])
            ->groupBy('day')
            ->get();

        foreach ($rows as $row) {
            $day = (int) date('j', strtotime((string) $row->day));
            $dailyBenefit[$day] = (float) ($row->benefit_sum ?? 0);
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Benefit',
                    'data' => array_values($dailyBenefit),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
