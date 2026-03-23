<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\LivreurResource;
use Filament\Resources\Pages\ListRecords;

class ListLivreurs extends ListRecords
{
    protected static string $resource = LivreurResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
