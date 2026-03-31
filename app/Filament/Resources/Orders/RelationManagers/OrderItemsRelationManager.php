<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';

    protected static ?string $title = 'المنتجات';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'productVariation']))
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\ImageColumn::make('product.main_image')
                    ->label('الصورة')
                    ->getStateUsing(fn ($record): string => $record->product?->mainImageUrl() ?? asset('images/placeholder-product.svg'))
                    ->circular(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('المنتج')
                    ->searchable(),
                Tables\Columns\TextColumn::make('variation_label')
                    ->label('النوع')
                    ->getStateUsing(fn ($record): string => $record->productVariation?->label() ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('الكمية'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('السعر')
                    ->money('MAD'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
