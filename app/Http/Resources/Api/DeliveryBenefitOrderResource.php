<?php

namespace App\Http\Resources\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class DeliveryBenefitOrderResource extends JsonResource
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
            'delivery_fee' => (float) $this->delivery_fee,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
