<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingSetting extends Model
{
    protected $fillable = [
        'casablanca_fee',
        'other_cities_fee',
    ];

    protected function casts(): array
    {
        return [
            'casablanca_fee' => 'decimal:2',
            'other_cities_fee' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row !== null) {
            return $row;
        }

        return static::query()->create([
            'casablanca_fee' => 20,
            'other_cities_fee' => 40,
        ]);
    }
}
