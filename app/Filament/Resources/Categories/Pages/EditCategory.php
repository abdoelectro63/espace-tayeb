<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * When FileUpload state is empty on save (Livewire/Filepond quirks), keep the existing path.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! filled($data['image'] ?? null)) {
            $data['image'] = $this->record->image;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
