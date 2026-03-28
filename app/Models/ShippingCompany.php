<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingCompany extends Model
{
    protected $fillable = [
        'name',
        'color',
        'store_id',
        'token',
        'delivery_token',
        'vitips_token',
    ];

    public function getApiTokenAttribute(): ?string
    {
        $token = $this->delivery_token
            ?? $this->token
            ?? $this->vitips_token;

        return filled($token) ? (string) $token : null;
    }

    public function cities(): HasMany
    {
        return $this->hasMany(ShippingCompanyCity::class);
    }
}
