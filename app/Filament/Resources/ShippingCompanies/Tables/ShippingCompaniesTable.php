<?php

namespace App\Filament\Resources\ShippingCompanies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ShippingCompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Company')
                    ->badge()
                    ->color(fn ($record): string => $record->color ?: 'info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('color')
                    ->label('Color')
                    ->badge()
                    ->color(fn ($record): string => $record->color ?: 'info'),
                TextColumn::make('store_id')
                    ->label('Store ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('token')
                    ->label('API Token')
                    ->formatStateUsing(function ($state): string {
                        if (blank($state)) {
                            return '—';
                        }

                        $token = (string) $state;
                        return Str::mask($token, '*', 4, 4);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
