<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    protected $fillable = [
        'seller_company_name',
        'seller_address',
        'seller_ice',
        'seller_if',
        'seller_rc',
        'seller_patente',
        'seller_rib',
        'default_tva_rate',
    ];

    protected function casts(): array
    {
        return [
            'default_tva_rate' => 'decimal:2',
        ];
    }

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'default_tva_rate' => 14,
            ]
        );
    }
}
