<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    public const LOCATION_TOP_MENU = 'top_menu';

    public const LOCATION_FOOTER_1 = 'footer_1';

    public const LOCATION_FOOTER_2 = 'footer_2';

    public const LOCATION_FOOTER_3 = 'footer_3';

    /** @return array<string, string> */
    public static function locationOptions(): array
    {
        return [
            self::LOCATION_TOP_MENU => 'Top menu',
            self::LOCATION_FOOTER_1 => 'Footer column 1',
            self::LOCATION_FOOTER_2 => 'Footer column 2',
            self::LOCATION_FOOTER_3 => 'Footer column 3',
        ];
    }

    protected $fillable = [
        'name',
        'location',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('order');
    }
}
