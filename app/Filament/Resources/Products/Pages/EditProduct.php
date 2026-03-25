<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * When FileUpload state is missing or null (Livewire/Filepond quirks on edit),
     * keep existing paths so images are not wiped from the database.
     * An explicit empty gallery array [] is left as-is (user removed all files).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! filled($data['main_image'] ?? null)) {
            $data['main_image'] = $this->record->main_image;
        }

        if (! array_key_exists('images', $data) || $data['images'] === null) {
            $data['images'] = $this->record->images ?? [];
        } elseif (is_array($data['images'])) {
            $data['images'] = array_values(array_filter($data['images'], fn ($path) => filled($path)));
        }

        if (! ($data['track_stock'] ?? false)) {
            $data['stock'] = 0;
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
