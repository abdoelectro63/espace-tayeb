<?php

namespace App\Filament\Resources\Orders;

use App\Models\Order;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;
use App\Filament\Resources\Orders\Pages;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\DeliveriesTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Grouping\Group;
use BackedEnum; // ضروري لحل مشكلة النوع (Type)
use UnitEnum;

class DeliveryResource extends Resource
{
    protected static ?string $model = Order::class;

    // تصحيح النوع هنا ليتوافق مع الكلاس الأصلي
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Delivery - Livraison';

    protected static string|UnitEnum|null $navigationGroup = 'توصيلs';
    
    protected static ?string $modelLabel = 'توصيل';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveriesTable::configure($table)
            ->groups([
                Group::make('deliveryMan.name')
                    ->label('الموزع')
                    ->collapsible(),
            ])
            ->defaultGroup('deliveryMan.name');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $query
            ->whereIn('status', ['confirmed', 'shipped', 'delivered']);

        if (auth()->user()?->role === 'delivery_man') {
            $query->where('delivery_man_id', auth()->id());
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getModel()::query()
            ->whereIn('status', ['shipped', 'delivered'])
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('payment_status')
                    ->orWhere('payment_status', '!=', 'paid');
            });

        if (auth()->user()?->role === 'delivery_man') {
            $query->where('delivery_man_id', auth()->id());
        }

        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'delivery_man', 'confirmation'], true);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveries::route('/'),
        ];
    }
}