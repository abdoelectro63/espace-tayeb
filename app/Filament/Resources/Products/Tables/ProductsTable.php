<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                /*              Tables\Columns\ImageColumn::make('images')
                    ->label('صور المنتج')
                    ->stacked() // سيعرض الصور فوق بعضها بشكل أنيق
                    ->limit(3) // يعرض أول 3 صور فقط في الجدول
                    ->circular(), // يعرض الصور بشكل دائري */
                Tables\Columns\ImageColumn::make('main_image')
                    ->label('صورة المنتج الرئيسية')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم المنتج')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('الثمن الأصلي')
                    ->money('MAD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_price')
                    ->label('ثمن العرض')
                    ->money('MAD')
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('free_shipping')
                    ->label('التوصيل')
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'مجاني' : 'مدفوع')
                    ->badge()
                    ->color(fn (?bool $state): string => $state ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('عرض في المتجر'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('المخزون')
                    ->formatStateUsing(function ($state, Product $record): string {
                        if (! $record->track_stock) {
                            return 'متوفر';
                        }

                        return (string) $state;
                    })
                    ->sortable()
                    ->color(fn (Product $record): string => ! $record->track_stock
                        ? 'success'
                        : ((int) $record->stock <= 5 ? 'danger' : 'success')),

            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
