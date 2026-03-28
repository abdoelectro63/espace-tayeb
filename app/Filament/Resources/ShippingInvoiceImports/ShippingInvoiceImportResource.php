<?php

namespace App\Filament\Resources\ShippingInvoiceImports;

use App\Filament\Resources\ShippingInvoiceImports\Pages\ListShippingInvoiceImports;
use App\Filament\Resources\ShippingInvoiceImports\Pages\ViewShippingInvoiceImport;
use App\Filament\Resources\ShippingInvoiceImports\RelationManagers\LinesRelationManager;
use App\Models\ShippingInvoiceImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShippingInvoiceImportResource extends Resource
{
    protected static ?string $model = ShippingInvoiceImport::class;

    /**
     * Accès depuis Livraisons → onglet Shipping Companies (pas dans le menu latéral).
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'فواتير الشحن';

    protected static ?string $modelLabel = 'استيراد فاتورة';

    protected static ?string $pluralModelLabel = 'استيرادات فواتير الشحن';

    protected static string|UnitEnum|null $navigationGroup = 'توصيلs';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->placeholder('—'),
                TextColumn::make('carrier_filter')
                    ->label('النطاق')
                    ->badge(),
                TextColumn::make('lines_count')
                    ->label('أسطر الفاتورة'),
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas(
                'lines',
                fn (Builder $query): Builder => $query->whereNotIn('match_status', ['not_found', 'already_paid'])
            )
            ->withCount([
                'lines as lines_count' => fn (Builder $query): Builder => $query->whereNotIn('match_status', ['not_found', 'already_paid']),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingInvoiceImports::route('/'),
            'view' => ViewShippingInvoiceImport::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'confirmation'], true);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
