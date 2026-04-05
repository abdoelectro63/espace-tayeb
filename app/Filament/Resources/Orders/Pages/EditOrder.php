<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
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
        $data['shipping_fee'] = (float) ($record->shipping_fee ?? 0);
        $data['total_price'] = round(
            OrderForm::calculateTotalFromItems($itemsForCalc) + (float) $data['shipping_fee'],
            2
        );
        $data['_free_shipping'] = ((float) $data['shipping_fee']) <= 0.0;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
