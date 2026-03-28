<?php

namespace App\Filament\Resources\ShippingInvoiceImports\Pages;

use App\Filament\Resources\ShippingInvoiceImports\ShippingInvoiceImportResource;
use App\Services\Shipping\ShippingInvoiceImporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewShippingInvoiceImport extends ViewRecord
{
    protected static string $resource = ShippingInvoiceImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('collectVitips')
                ->label('تحصيل أموال — Vitips')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->lines()->exists()
                    && $this->getRecord()->eligibleVitipsCount() > 0)
                ->requiresConfirmation()
                ->modalHeading('تحصيل أموال Vitips')
                ->modalDescription('سيتم تسجيل الطلبيات المؤهّلة كـ تم التسليم + مدفوع (Frais > 0 ومطابقة شحن).')
                ->action(function (ShippingInvoiceImporter $importer): void {
                    $n = $importer->collectFundsForCarrier($this->getRecord(), 'vitips');
                    Notification::make()
                        ->title($n > 0 ? "تم تحصيل {$n} طلبية Vitips" : 'لا توجد طلبية قابلة للتحصيل')
                        ->{$n > 0 ? 'success' : 'warning'}()
                        ->send();
                }),
            Action::make('collectExpress')
                ->label('تحصيل أموال — Express Coursier')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->lines()->exists()
                    && $this->getRecord()->eligibleExpressCount() > 0)
                ->requiresConfirmation()
                ->modalHeading('تحصيل أموال Express Coursier')
                ->modalDescription('سيتم تسجيل الطلبيات المؤهّلة كـ تم التسليم + مدفوع.')
                ->action(function (ShippingInvoiceImporter $importer): void {
                    $n = $importer->collectFundsForCarrier($this->getRecord(), 'express');
                    Notification::make()
                        ->title($n > 0 ? "تم تحصيل {$n} طلبية Express" : 'لا توجد طلبية قابلة للتحصيل')
                        ->{$n > 0 ? 'success' : 'warning'}()
                        ->send();
                }),
        ];
    }
}
