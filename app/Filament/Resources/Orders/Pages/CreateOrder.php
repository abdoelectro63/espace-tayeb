<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Services\ShippingCalculator;
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
        $items = $this->resolveOrderItemsForCalculation($data);
        $zone = (string) ($data['shipping_zone'] ?? 'casablanca');
        $data['shipping_fee'] = ShippingCalculator::feeForAdminOrderItems($items, $zone);
        $data['total_price'] = round(
            OrderForm::calculateTotalFromItems($items) + (float) $data['shipping_fee'],
            2
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $record->loadMissing('orderItems');

        $itemsForCalc = $record->orderItems->map(fn ($item): array => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ])->all();

        $zone = (string) ($record->shipping_zone ?? 'casablanca');
        $shippingFee = ShippingCalculator::feeForAdminOrderItems($itemsForCalc, $zone);
        $total = round(OrderForm::calculateTotalFromItems($itemsForCalc) + $shippingFee, 2);

        $record->update([
            'shipping_fee' => $shippingFee,
            'total_price' => $total,
        ]);
    }
}
