<?php

namespace App\Filament\Resources\ShippingInvoiceImports\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'أسطر الفاتورة والطلبيات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotIn('match_status', ['not_found', 'already_paid']))
            ->columns([
                TextColumn::make('carrier')
                    ->label('الناقل')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'vitips' => 'Vitips',
                        'express' => 'Express Coursier',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'vitips' => 'warning',
                        'express' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('tracking_key')
                    ->label('التتبع / الكوليس')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('customer_name')
                    ->label('العميل')
                    ->placeholder('—'),
                TextColumn::make('city')
                    ->label('المدينة')
                    ->placeholder('—'),
                TextColumn::make('total_price')
                    ->label('المبلغ (الطلبية)')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== ''
                        ? number_format((float) $state, 2).' MAD'
                        : '—'),
                TextColumn::make('invoice_frais')
                    ->label('Frais (رسوم الشحن)')
                    ->suffix(' DH')
                    ->sortable(),
                TextColumn::make('etat')
                    ->label('الحالة (فاتورة)')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('match_status')
                    ->label('المطابقة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'eligible' => 'قابل للتحصيل',
                        'collected' => 'تم التحصيل',
                        'skipped_zero' => 'Frais صفر',
                        'ineligible' => 'غير مؤهل (Vitips)',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'eligible' => 'success',
                        'collected' => 'gray',
                        'skipped_zero' => 'warning',
                        'ineligible' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('order_id')
                    ->label('طلبية #')
                    ->url(fn ($state): ?string => $state ? OrderResource::getUrl('edit', ['record' => $state]) : null)
                    ->placeholder('—')
                    ->color('primary'),
                TextColumn::make('collected_at')
                    ->label('تاريخ التحصيل')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('id')
            ->paginated([10, 25, 50])
            ->headerActions([]);
    }
}
