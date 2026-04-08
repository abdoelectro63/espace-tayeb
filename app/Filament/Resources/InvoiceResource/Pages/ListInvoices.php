<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Pages\InvoiceSettingsPage;
use App\Filament\Resources\InvoiceResource;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public static function configureTable(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('N° commande')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Client')
                    ->searchable(),
                Tables\Columns\TextColumn::make('shippingCompany.name')
                    ->label('Société de livraison')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deliveryMan.name')
                    ->label('Livreur')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total TTC')
                    ->money('MAD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_status')
                    ->label('Facturation')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'invoiced' => 'Facturé',
                        default => 'Non facturé',
                    })
                    ->color(fn (?string $state): string => $state === 'invoiced' ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shipping_company_id')
                    ->label('Société de livraison')
                    ->relationship('shippingCompany', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('delivery_man_id')
                    ->label('Livreur')
                    ->options(fn (): array => User::query()
                        ->where('role', 'delivery_man')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('invoiceClient')
                    ->label('Client & lignes')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Order $record): string => InvoiceResource::getUrl('manage_invoice', ['record' => $record])),
                Action::make('preview')
                    ->label('Aperçu PDF')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record): string => route('invoices.orders.pdf', ['order' => $record]))
                    ->openUrlInNewTab(),
                Action::make('markInvoiced')
                    ->label('Facturé')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record): bool => ($record->invoice_status ?? 'not_invoiced') !== 'invoiced')
                    ->requiresConfirmation()
                    ->modalHeading('Marquer comme facturé')
                    ->modalDescription('Le statut passera à « Facturé » (vert).')
                    ->action(function (Order $record): void {
                        $record->update([
                            'invoice_status' => 'invoiced',
                            'invoiced_at' => now(),
                        ]);
                        Notification::make()->title('Facture enregistrée')->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Paramètres facture')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(InvoiceSettingsPage::getUrl()),
        ];
    }
}
