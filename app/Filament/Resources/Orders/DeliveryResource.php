<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\DeliveriesTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder; // ضروري لحل مشكلة النوع (Type)
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

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->role === 'delivery_man'
            ? 'Orders'
            : (static::$navigationLabel ?? 'Delivery');
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveriesTable::configure($table)
            ->groups([
                Group::make('delivery_man_id')
                    ->label('الموزع')
                    ->collapsible()
                    ->getKeyFromRecordUsing(function (Order $record): string {
                        if ($record->delivery_man_id !== null) {
                            return (string) $record->delivery_man_id;
                        }

                        return 'sc:'.($record->shipping_company ?? '');
                    })
                    ->getTitleFromRecordUsing(fn (Order $record): string => $record->deliveryMan?->name ?: ($record->shipping_company ?: 'غير معين'))
                    // Same group title can repeat if rows order is e.g. موزع → شركة شحن (null) → نفس الموزع.
                    ->orderQueryUsing(function (EloquentBuilder $query, string $direction): EloquentBuilder {
                        return $query
                            ->orderByRaw('CASE WHEN delivery_man_id IS NULL THEN 1 ELSE 0 END')
                            ->orderBy('delivery_man_id', $direction)
                            ->orderBy('shipping_company')
                            ->orderByDesc('created_at');
                    })
                    ->scopeQueryByKeyUsing(function (EloquentBuilder $query, string $column, ?string $key): EloquentBuilder {
                        if (blank($key)) {
                            return $query;
                        }

                        if (str_starts_with($key, 'sc:')) {
                            $company = substr($key, 3);

                            return $query
                                ->whereNull('delivery_man_id')
                                ->when(
                                    $company !== '',
                                    fn (EloquentBuilder $q): EloquentBuilder => $q->where('shipping_company', $company),
                                    fn (EloquentBuilder $q): EloquentBuilder => $q->where(function (EloquentBuilder $inner): void {
                                        $inner->whereNull('shipping_company')->orWhere('shipping_company', '');
                                    }),
                                );
                        }

                        return $query->where('delivery_man_id', (int) $key);
                    }),
            ])
            ->defaultGroup('delivery_man_id');
    }

    public static function getEloquentQuery(): EloquentBuilder
    {
        $query = parent::getEloquentQuery();

        $query
            ->whereIn('status', ['confirmed', 'shipped', 'delivered', 'no_response', 'cancelled', 'refuse', 'reporter'])
            ->with('deliveryMan');

        if (auth()->user()?->role === 'delivery_man') {
            $query->where('delivery_man_id', auth()->id());
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getModel()::query()
            ->whereIn('status', ['shipped', 'delivered'])
            ->where(function (EloquentBuilder $builder): void {
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
        return in_array(auth()->user()?->role, ['admin', 'delivery_man', 'confirmation', 'manager'], true);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveries::route('/'),
        ];
    }
}
