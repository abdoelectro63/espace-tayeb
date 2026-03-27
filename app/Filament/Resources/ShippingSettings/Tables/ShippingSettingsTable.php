<?php

namespace App\Filament\Resources\ShippingSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->circular(),
                Tables\Columns\TextColumn::make('casablanca_fee')
                    ->label('الدار البيضاء')
                    ->suffix(' MAD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('other_cities_fee')
                    ->label('مدن أخرى')
                    ->suffix(' MAD')
                    ->sortable(),
                Tables\Columns\ColorColumn::make('header_bg_color')
                    ->label('لون الخلفية'),
                Tables\Columns\ColorColumn::make('menu_text_color')
                    ->label('لون النص'),
                Tables\Columns\ImageColumn::make('hero_banner_path')
                    ->label('بانر الرئيسية')
                    ->disk('public'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->paginated(false);
    }
}
