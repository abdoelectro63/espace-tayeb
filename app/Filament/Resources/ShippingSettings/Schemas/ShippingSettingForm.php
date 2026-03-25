<?php

namespace App\Filament\Resources\ShippingSettings\Schemas;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('التوصيل داخل المغرب')
                    ->description('يتم احتساب رسوم التوصيل حسب المدينة (الدار البيضاء أو باقي المدن). يمكن تعديل المبالغ في أي وقت.')
                    ->schema([
                        Forms\Components\TextInput::make('casablanca_fee')
                            ->label('الدار البيضاء (MAD)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->suffix('DH'),

                        Forms\Components\TextInput::make('other_cities_fee')
                            ->label('مدن أخرى بالمغرب (MAD)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->suffix('DH'),
                    ])
                    ->columns(2),
            ]);
    }
}
