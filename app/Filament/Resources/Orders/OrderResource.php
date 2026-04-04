<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon; // نلتزم بـ Schema كما في الخطأ
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'الطلبيات';

    protected static ?string $modelLabel = 'طلبية';

    protected static ?string $pluralModelLabel = 'الطلبيات';

    // إخفاء المحذوفات من البحث العام والاستعلامات الافتراضية
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // تأكد أن النوع هنا هو Schema ليتوافق مع الكلاس الأب (Resource)
    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'confirmation', 'manager'], true);
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if ($record instanceof Order && in_array($record->status, ['delivered', 'completed'], true)) {
            return Response::deny('لا يمكن تعديل طلبية تم تسليمها أو إقفالها.');
        }

        return parent::getEditAuthorizationResponse($record);
    }
}
