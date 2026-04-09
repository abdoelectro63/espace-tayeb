<?php

namespace App\Filament\Resources\ManualInvoices;

use App\Filament\Resources\ManualInvoices\Pages\CreateManualInvoice;
use App\Filament\Resources\ManualInvoices\Pages\EditManualInvoice;
use App\Filament\Resources\ManualInvoices\Pages\ListManualInvoices;
use App\Filament\Resources\ManualInvoices\Schemas\ManualInvoiceForm;
use App\Models\ManualInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ManualInvoiceResource extends Resource
{
    protected static ?string $model = ManualInvoice::class;

    protected static ?string $slug = 'manual-invoices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Manuel facture';

    protected static ?string $modelLabel = 'Facture manuelle';

    protected static ?string $pluralModelLabel = 'Factures manuelles';

    protected static string|UnitEnum|null $navigationGroup = 'Facturation';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ManualInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListManualInvoices::configureTable($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManualInvoices::route('/'),
            'create' => CreateManualInvoice::route('/create'),
            'edit' => EditManualInvoice::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<ManualInvoice>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with('lines');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        if (! static::canViewAny()) {
            return false;
        }

        if ($record instanceof ManualInvoice && $record->trashed()) {
            return false;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }
}
