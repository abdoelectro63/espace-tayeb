<?php

namespace App\Filament\Resources\ShippingSettings\Pages;

use App\Filament\Resources\ShippingSettings\ShippingSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditShippingSetting extends EditRecord
{
    protected static string $resource = ShippingSettingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! filled($data['logo_path'] ?? null)) {
            $data['logo_path'] = $this->record->logo_path;
        }
        if (! filled($data['hero_banner_path'] ?? null)) {
            $data['hero_banner_path'] = $this->record->hero_banner_path;
        }

        return $data;
    }
}
