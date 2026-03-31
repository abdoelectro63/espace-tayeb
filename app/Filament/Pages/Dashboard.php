<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DeliveryManDashboardBenefitChart;
use App\Filament\Widgets\DeliveryManDashboardStats;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Route;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return true;
    }

    public function mount(): void
    {
        if (auth()->user()?->role !== 'manager') {
            return;
        }

        if (Route::has('filament.admin.resources.orders.index')) {
            $this->redirect(route('filament.admin.resources.orders.index'), navigate: true);

            return;
        }

        if (Route::has('filament.admin.resources.deliveries.index')) {
            $this->redirect(route('filament.admin.resources.deliveries.index'), navigate: true);

            return;
        }

        $this->redirect('/admin/orders', navigate: true);
    }

    protected function getHeaderWidgets(): array
    {
        if (auth()->user()?->role !== 'delivery_man') {
            return parent::getHeaderWidgets();
        }

        return [
            DeliveryManDashboardStats::class,
            DeliveryManDashboardBenefitChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        if (auth()->user()?->role !== 'delivery_man') {
            return parent::getHeaderWidgetsColumns();
        }

        return [
            'md' => 1,
            'xl' => 2,
        ];
    }
}
