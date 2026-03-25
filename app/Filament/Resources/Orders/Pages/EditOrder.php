<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Services\ShippingCalculator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $record->loadMissing('orderItems');

        $itemsForCalc = $record->orderItems->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ])->all();

        $zone = $record->shipping_zone ?? 'casablanca';
        $data['shipping_zone'] = $zone;
        $data['shipping_fee'] = ShippingCalculator::feeForAdminOrderItems($itemsForCalc, $zone);
        $data['total_price'] = round(
            OrderForm::calculateTotalFromItems($itemsForCalc) + (float) $data['shipping_fee'],
            2
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
