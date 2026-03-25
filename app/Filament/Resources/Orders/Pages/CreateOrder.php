<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Services\ShippingCalculator;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $items = $data['orderItems'] ?? [];
        $zone = (string) ($data['shipping_zone'] ?? 'casablanca');
        $data['shipping_fee'] = ShippingCalculator::feeForAdminOrderItems($items, $zone);
        $data['total_price'] = round(
            OrderForm::calculateTotalFromItems($items) + (float) $data['shipping_fee'],
            2
        );

        return $data;
    }
}
