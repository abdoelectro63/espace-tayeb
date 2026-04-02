<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSetting extends Model
{
    protected $fillable = [
        'page_title',
        'phone',
        'email',
        'address',
        'map_embed_html',
        'seo_title',
        'seo_description',
    ];

    /**
     * Single-row settings for the public contact page.
     */
    public static function settings(): self
    {
        $row = static::query()->first();

        if ($row !== null) {
            return $row;
        }

        return static::query()->create([
            'page_title' => 'اتصل بنا',
            'phone' => null,
            'email' => null,
            'address' => null,
            'map_embed_html' => null,
            'seo_title' => null,
            'seo_description' => null,
        ]);
    }
}
