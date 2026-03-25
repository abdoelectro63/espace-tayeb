<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\DeliveryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return []; // الموزع لا يحتاج لزر "إنشاء طلب جديد" هنا
    }

    public function getTabs(): array
    {
        $baseQuery = DeliveryResource::getEloquentQuery();

        return [
            'pending' => Tab::make('Pending')
                ->badge((clone $baseQuery)
                    ->where('status', 'confirmed')
                    ->whereNull('delivery_man_id')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'confirmed')
                    ->whereNull('delivery_man_id')
                    ->latest('created_at')),
            'local_delivery' => Tab::make('Local Delivery')
                ->badge((clone $baseQuery)
                    ->where('status', 'shipped')
                    ->whereNotNull('delivery_man_id')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'shipped')
                    ->whereNotNull('delivery_man_id')
                    ->orderBy('delivery_man_id')
                    ->latest('created_at')),
            'shipping_companies' => Tab::make('Shipping Companies')
                ->badge((clone $baseQuery)
                    ->where('status', 'shipped')
                    ->whereNotNull('shipping_company')
                    ->where('shipping_company', '!=', '')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'shipped')
                    ->whereNotNull('shipping_company')
                    ->where('shipping_company', '!=', '')
                    ->latest('created_at')),
            'collection' => Tab::make('Collection')
                ->badge((clone $baseQuery)
                    ->where('status', 'delivered')
                    ->where('payment_status', 'unpaid')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'delivered')
                    ->where('payment_status', 'unpaid')
                    ->latest('created_at')),
            'completed' => Tab::make('Completed')
                ->badge((clone $baseQuery)
                    ->where('payment_status', 'paid')
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('payment_status', 'paid')
                    ->latest('created_at')),
        ];
    }
}