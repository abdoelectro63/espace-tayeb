<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCarrierCitySelection extends Model
{
    protected $fillable = [
        'order_id',
        'shipping_company_id',
        'shipping_company_city_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    public function shippingCompanyCity(): BelongsTo
    {
        return $this->belongsTo(ShippingCompanyCity::class);
    }
}
