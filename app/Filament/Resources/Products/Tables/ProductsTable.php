<?php

namespace App\Filament\Resources\Products\Tables;

use App\Exports\ProductExampleExport;
use App\Imports\ProductImport;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

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
                    ->getStateUsing(fn (Product $record): string => $record->mainImageUrl())
                    ->defaultImageUrl(asset('images/placeholder-product.svg'))
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم المنتج')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code Produit')
                    ->badge()
                    ->color('info')
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

                Tables\Columns\TextColumn::make('offer_type')
                    ->label('عرض إضافي')
                    ->formatStateUsing(function (?string $state, Product $record): string {
                        if ($state === Product::OFFER_PERCENTAGE && $record->offer_value !== null) {
                            return 'خصم %'.(string) $record->offer_value;
                        }

                        return match ($state) {
                            Product::OFFER_FREE_DELIVERY => 'توصيل مجاني',
                            Product::OFFER_PERCENTAGE => 'خصم %',
                            default => '—',
                        };
                    })
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        Product::OFFER_PERCENTAGE => 'warning',
                        Product::OFFER_FREE_DELIVERY => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('upsellProduct.name')
                    ->label('منتج مجمّع')
                    ->placeholder('—')
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
            ->headerActions([
                Action::make('importProducts')
                    ->label('Import Products')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->button()
                    ->extraAttributes(['style' => 'background-color:#ff751f;border-color:#ff751f;color:#fff'])
                    ->form([
                        FileUpload::make('file')
                            ->label('Excel / CSV')
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                                'text/plain',
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $path = (string) ($data['file'] ?? '');
                        if ($path === '' || ! Storage::disk('local')->exists($path)) {
                            Notification::make()
                                ->title('No file found to import.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $import = new ProductImport;
                        Excel::import($import, $path, 'local');

                        Notification::make()
                            ->title("Import completed: {$import->importedCount()} row(s), {$import->failuresCount()} failure(s).")
                            ->warning()
                            ->send();
                    }),

                Action::make('downloadExample')
                    ->label('Download Example')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->button()
                    ->extraAttributes(['style' => 'background-color:#ff751f;border-color:#ff751f;color:#fff'])
                    ->action(fn () => Excel::download(new ProductExampleExport, 'product_example.xlsx')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
