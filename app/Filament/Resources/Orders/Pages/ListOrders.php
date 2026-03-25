<?php

namespace App\Filament\Resources\Orders\Pages;

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
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->label('الطلبات النشطة')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->withoutTrashed()
                    ->whereNotIn('status', ['shipped', 'delivered'])),

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
