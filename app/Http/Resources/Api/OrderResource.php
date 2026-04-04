<?php

namespace App\Http\Resources\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'city' => $this->city,
            'shipping_address' => $this->shipping_address,
            'shipping_zone' => $this->shipping_zone,
            'total_price' => (float) $this->total_price,
            'shipping_fee' => $this->shipping_fee !== null ? (float) $this->shipping_fee : null,
            'delivery_fee' => $this->delivery_fee !== null ? (float) $this->delivery_fee : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'payment_status' => $this->payment_status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'tracking_number' => $this->tracking_number,
            'shipping_company' => $this->shipping_company,
            'shipping_provider_status' => $this->shipping_provider_status,
            'delivery_man_id' => $this->delivery_man_id,
            'delivery_man' => $this->whenLoaded('deliveryMan', fn (): ?array => $this->deliveryMan ? [
                'id' => $this->deliveryMan->id,
                'name' => $this->deliveryMan->name,
            ] : null),
            'items' => $this->whenLoaded('orderItems', function () {
                return $this->orderItems->map(function ($line): array {
                    return [
                        'id' => $line->id,
                        'product_id' => $line->product_id,
                        'product_variation_id' => $line->product_variation_id,
                        'product_name' => $line->product?->name,
                        'quantity' => (int) $line->quantity,
                        'unit_price' => (float) $line->unit_price,
                    ];
                })->values()->all();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
