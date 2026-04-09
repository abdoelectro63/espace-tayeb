<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class TrackingSettings extends Settings
{
    public string $facebook_pixel_id;

    public string $facebook_access_token;

    public string $facebook_test_event_code;

    public string $tiktok_pixel_id;

    public string $tiktok_access_token;

    public bool $tracking_debug;

    public static function group(): string
    {
        return 'tracking';
    }
}
