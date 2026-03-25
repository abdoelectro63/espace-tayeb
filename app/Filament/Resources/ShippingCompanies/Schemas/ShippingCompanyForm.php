<?php

namespace App\Filament\Resources\ShippingCompanies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ShippingCompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Company Name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('color')
                    ->label('Badge Color')
                    ->options([
                        'gray' => 'Gray',
                        'info' => 'Blue',
                        'success' => 'Green',
                        'warning' => 'Orange',
                        'danger' => 'Red',
                        'primary' => 'Primary',
                    ])
                    ->default('info')
                    ->required(),
            ]);
    }
}
