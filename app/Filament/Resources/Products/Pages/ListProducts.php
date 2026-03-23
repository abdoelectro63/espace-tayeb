<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Full create page: modal create can submit empty data for complex forms (file uploads, sections).
            CreateAction::make()
                ->modal(false)
                ->url(fn (): string => static::getResource()::getUrl('create')),
        ];
    }
}
