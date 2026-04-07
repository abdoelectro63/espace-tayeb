<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Pages\ImportOrdersCsv;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importCsv')
                ->label('استيراد CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(ImportOrdersCsv::getUrl())
                ->visible(fn (): bool => OrderResource::canViewAny()),
            Actions\CreateAction::make()
                ->visible(fn (): bool => ($this->activeTab ?? null) !== 'delivered'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الطلبات النشطة')
                ->label('الطلبات النشطة')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->withoutTrashed()
                    ->whereIn('status', ['pending', 'confirmed'])),

            'needs_followup' => Tab::make('طلبيات تحتاج الى تتبع')
                ->label('طلبيات تحتاج الى تتبع')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->withoutTrashed()
                    ->whereIn('status', ['no_response', 'cancelled'])),

            'delivered' => Tab::make('تم التسليم')
                ->label('الطلبات المسلمة')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->withoutTrashed()
                    ->where('status', 'delivered')),

            'trash' => Tab::make('سلة المحذوفات')
                ->label('المحذوفات')
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed())
                ->icon('heroicon-m-trash'),
        ];
    }
}
