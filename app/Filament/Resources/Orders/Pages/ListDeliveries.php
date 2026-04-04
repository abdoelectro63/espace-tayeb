<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\DeliveryResource;
use App\Filament\Resources\Orders\Widgets\DeliveryMonthlyFeesChart;
use App\Filament\Resources\Orders\Widgets\DeliveryMonthlyStatsOverview;
use App\Filament\Resources\ShippingInvoiceImports\ShippingInvoiceImportResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderWidgets(): array
    {
        if (! in_array(auth()->user()?->role, ['admin', 'confirmation', 'delivery_man'], true)) {
            return [];
        }

        return [
            DeliveryMonthlyStatsOverview::class,
            DeliveryMonthlyFeesChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 1,
            'xl' => 2,
        ];
    }

    protected function getHeaderActions(): array
    {
        if (auth()->user()?->role === 'delivery_man') {
            return [];
        }

        if (($this->activeTab ?? null) !== 'shipping_companies') {
            return [];
        }

        if (! in_array(auth()->user()?->role, ['admin', 'confirmation'], true)) {
            return [];
        }

        return [
            Action::make('shipping_invoice_imports')
                ->label('فواتير الشحن')
                ->icon('heroicon-o-document-text')
                ->url(ShippingInvoiceImportResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = DeliveryResource::getEloquentQuery();
        $unpaidScope = fn (Builder $query): Builder => $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('payment_status')
                ->orWhere('payment_status', '!=', 'paid');
        });

        if (auth()->user()?->role === 'delivery_man') {
            $driverActive = fn (Builder $query): Builder => $unpaidScope($query
                ->whereIn('status', Order::DELIVERY_PANEL_STATUSES));

            return [
                'my_orders' => Tab::make('My Orders')
                    ->badge($driverActive(clone $baseQuery)->count())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $driverActive($query)
                        ->latest('created_at')),
                'completed' => Tab::make('Completed')
                    ->badge((clone $baseQuery)
                        ->where(function (Builder $q): void {
                            $q->where('status', 'completed')
                                ->orWhere(function (Builder $inner): void {
                                    $inner->where('status', 'delivered')->where('payment_status', 'paid');
                                });
                        })
                        ->count())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query
                        ->where(function (Builder $q): void {
                            $q->where('status', 'completed')
                                ->orWhere(function (Builder $inner): void {
                                    $inner->where('status', 'delivered')->where('payment_status', 'paid');
                                });
                        })
                        ->latest('created_at')),
            ];
        }

        return [
            'pending' => Tab::make('Pending')
                ->badge($unpaidScope((clone $baseQuery)
                    ->where('status', 'confirmed')
                    ->whereNull('delivery_man_id'))
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $unpaidScope($query
                    ->where('status', 'confirmed')
                    ->whereNull('delivery_man_id')
                    ->latest('created_at'))),
            'local_delivery' => Tab::make('Local Delivery')
                ->badge($unpaidScope((clone $baseQuery)
                    ->whereIn('status', ['confirmed', 'shipped', 'no_response', 'cancelled', 'refuse', 'reporter'])
                    ->whereNotNull('delivery_man_id'))
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $unpaidScope($query
                    ->whereIn('status', ['confirmed', 'shipped', 'no_response', 'cancelled', 'refuse', 'reporter'])
                    ->whereNotNull('delivery_man_id')
                    ->orderBy('delivery_man_id')
                    ->latest('created_at'))),
            'shipping_companies' => Tab::make('Shipping Companies')
                ->badge($unpaidScope((clone $baseQuery)
                    ->where('status', 'shipped')
                    ->whereNotNull('shipping_company')
                    ->where('shipping_company', '!=', ''))
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $unpaidScope($query
                    ->where('status', 'shipped')
                    ->whereNotNull('shipping_company')
                    ->where('shipping_company', '!=', '')
                    ->latest('created_at'))),
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
                    ->where(function (Builder $q): void {
                        $q->where('status', 'completed')
                            ->orWhere(function (Builder $inner): void {
                                $inner->where('status', 'delivered')->where('payment_status', 'paid');
                            });
                    })
                    ->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where(function (Builder $q): void {
                        $q->where('status', 'completed')
                            ->orWhere(function (Builder $inner): void {
                                $inner->where('status', 'delivered')->where('payment_status', 'paid');
                            });
                    })
                    ->latest('created_at')),
        ];
    }
}
