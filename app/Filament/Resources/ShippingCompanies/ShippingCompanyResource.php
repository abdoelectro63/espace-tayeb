<?php

namespace App\Filament\Resources\ShippingCompanies;

use App\Filament\Resources\ShippingCompanies\Pages\CreateShippingCompany;
use App\Filament\Resources\ShippingCompanies\Pages\EditShippingCompany;
use App\Filament\Resources\ShippingCompanies\Pages\ListShippingCompanies;
use App\Filament\Resources\ShippingCompanies\Schemas\ShippingCompanyForm;
use App\Filament\Resources\ShippingCompanies\Tables\ShippingCompaniesTable;
use App\Models\ShippingCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ShippingCompanyResource extends Resource
{
    protected static ?string $model = ShippingCompany::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Shipping Companies';

    protected static ?string $modelLabel = 'Shipping Company';

    protected static ?string $pluralModelLabel = 'Shipping Companies';

    protected static string|UnitEnum|null $navigationGroup = 'توصيلs';

    public static function form(Schema $schema): Schema
    {
        return ShippingCompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingCompaniesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingCompanies::route('/'),
            'create' => CreateShippingCompany::route('/create'),
            'edit' => EditShippingCompany::route('/{record}/edit'),
        ];
    }
}
