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
        return [
            'pending_assignment' => Tab::make('طلبات بانتظار موزع')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNull('delivery_man_id')
                    ->where('status', 'confirmed')
                    ->latest('created_at')),
            'out_for_delivery' => Tab::make('طلبات قيد التوصيل')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('delivery_man_id')
                    ->orderBy('delivery_man_id')
                    ->latest('created_at')),
        ];
    }
}