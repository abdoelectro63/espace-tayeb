<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes; // أضف هذا

class Order extends Model
{
    use SoftDeletes; // أضف هذا السطر داخل الكلاس

    /**
     * English slugs stored in `orders.status` (align mobile `statusToApiPayload` with these).
     *
     * @var list<string>
     */
    public const STATUSES = [
        'pending',
        'confirmed',
        'no_response',
        'cancelled',
        'shipped',
        'delivered',
        'refuse',
        'reporter',
    ];

    protected $fillable = [
        'number', 'customer_name', 'customer_phone',
        'city', 'shipping_address', 'total_price',
        'shipping_fee', 'delivery_fee', 'shipping_zone',
        'status', 'notes', 'delivery_man_id', 'payment_status', 'paid_at',
        'shipping_company', 'shipping_company_id', 'tracking_number', 'shipping_provider_status',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'shipping_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $order): void {
            // Business rule: payment can only be marked paid after delivery.
            if ($order->status !== 'delivered' && $order->payment_status === 'paid') {
                $order->payment_status = 'unpaid';
                $order->paid_at = null;
            }
        });
    }

    /**
     * هذه هي العلاقة التي يحتاجها الـ Repeater
     */
    public function orderItems(): HasMany
    {
        // افترضنا أن اسم جدول الربط هو order_products أو order_items
        return $this->hasMany(OrderProduct::class);
    }

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }

    public function carrierCitySelections(): HasMany
    {
        return $this->hasMany(OrderCarrierCitySelection::class);
    }
}
