<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes; // أضف هذا

class Order extends Model
{
    use SoftDeletes; // أضف هذا السطر داخل الكلاس
    protected $fillable = [
        'number', 'customer_name', 'customer_phone', 
        'city', 'shipping_address', 'total_price', 
        'status', 'notes', 'delivery_man_id', 'payment_status', 'paid_at'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

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
}