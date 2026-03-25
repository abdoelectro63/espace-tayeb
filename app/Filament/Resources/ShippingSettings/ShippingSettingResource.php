<?php

namespace App\Filament\Resources\ShippingSettings;

use App\Filament\Resources\ShippingSettings\Pages\EditShippingSetting;
use App\Filament\Resources\ShippingSettings\Pages\ListShippingSettings;
use App\Filament\Resources\ShippingSettings\Schemas\ShippingSettingForm;
use App\Filament\Resources\ShippingSettings\Tables\ShippingSettingsTable;
use App\Models\ShippingSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ShippingSettingResource extends Resource
{
    protected static ?string $model = ShippingSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'إعدادات التوصيل';

    protected static ?string $modelLabel = 'إعدادات التوصيل';

    protected static ?string $pluralModelLabel = 'إعدادات التوصيل';

    protected static string|UnitEnum|null $navigationGroup = 'المتجر';

    public static function form(Schema $schema): Schema
    {
        return ShippingSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingSettings::route('/'),
            'edit' => EditShippingSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return ShippingSetting::query()->count() === 0;
    }

    public static function canViewAny(): bool
    {
        return ! in_array(auth()->user()?->role, ['confirmation', 'delivery_man'], true);
    }
}
