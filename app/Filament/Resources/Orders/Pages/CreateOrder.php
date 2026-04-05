<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function resolveOrderItemsForCalculation(array $data): array
    {
        $items = $data['orderItems'] ?? ($this->data['orderItems'] ?? []);

        return is_array($items) ? $items : [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['_free_shipping']);

        $items = $this->resolveOrderItemsForCalculation($data);
        $fee = max(0, round((float) ($data['shipping_fee'] ?? 0), 2));
        $data['shipping_fee'] = $fee;
        $data['total_price'] = round(
            OrderForm::calculateTotalFromItems($items) + $fee,
            2
        );

        return $data;
    }
}
