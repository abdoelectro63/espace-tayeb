<?php

namespace App\Filament\Resources\ManualInvoices\Pages;

use App\Filament\Pages\InvoiceSettingsPage;
use App\Filament\Resources\ManualInvoices\ManualInvoiceResource;
use App\Models\ManualInvoice;
use App\Services\Invoice\InvoiceAmountCalculator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;

class ListManualInvoices extends ListRecords
{
    protected static string $resource = ManualInvoiceResource::class;

    public static function configureTable(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('N°')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('client_company_name')
                    ->label('Client')
                    ->searchable()
                    ->wrap()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('total_ttc')
                    ->label('Total TTC')
                    ->state(function (ManualInvoice $record): string {
                        $amounts = InvoiceAmountCalculator::forManualInvoice($record);

                        return number_format($amounts['total_ttc'], 2, ',', ' ').' DHS';
                    }),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Supprimée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'trash'),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->recordUrl(
                fn (ManualInvoice $record): ?string => $record->trashed()
                    ? null
                    : ManualInvoiceResource::getUrl('edit', ['record' => $record])
            )
            ->recordActions([
                EditAction::make()
                    ->visible(fn (ManualInvoice $record): bool => ! $record->trashed()),
                Action::make('preview')
                    ->label('Aperçu PDF')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ManualInvoice $record): string => route('invoices.manual.pdf', ['manualInvoice' => $record]))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->visible(fn (ManualInvoice $record): bool => ! $record->trashed()),
                RestoreAction::make()
                    ->visible(fn (ManualInvoice $record): bool => $record->trashed()),
                ForceDeleteAction::make()
                    ->label('Supprimer définitivement')
                    ->visible(fn (ManualInvoice $record): bool => $record->trashed()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Mettre à la corbeille')
                        ->visible(fn (): bool => (Livewire::current()?->activeTab ?? 'active') === 'active'),
                    RestoreBulkAction::make()
                        ->label('Restaurer')
                        ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'trash'),
                    ForceDeleteBulkAction::make()
                        ->label('Supprimer définitivement')
                        ->visible(fn (): bool => (Livewire::current()?->activeTab ?? null) === 'trash'),
                ]),
            ]);
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Factures')
                ->label('Factures')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()),
            'trash' => Tab::make('Corbeille')
                ->label('Corbeille')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed())
                ->icon('heroicon-m-trash'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => ($this->activeTab ?? 'active') === 'active'),
            Action::make('settings')
                ->label('Paramètres facture')
                ->icon('heroicon-o-cog-6-tooth')
                ->url(InvoiceSettingsPage::getUrl()),
        ];
    }
}
