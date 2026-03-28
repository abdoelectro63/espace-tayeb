<?php

namespace App\Filament\Resources\ShippingInvoiceImports\Pages;

use App\Filament\Resources\ShippingInvoiceImports\ShippingInvoiceImportResource;
use Filament\Resources\Pages\ListRecords;

class ListShippingInvoiceImports extends ListRecords
{
    protected static string $resource = ShippingInvoiceImportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
