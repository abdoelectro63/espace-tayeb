<?php

use App\Settings\TrackingSettings;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    /**
     * Read tracking-related settings with request-level caching.
     *
     * @param  mixed  $default
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return app(TrackingSettings::class);
        }

        $cacheKey = 'setting.cache.'.$key;

        return Cache::remember($cacheKey, 3600, function () use ($key, $default): mixed {
            /** @var TrackingSettings $s */
            $s = app(TrackingSettings::class);

            return match ($key) {
                'facebook_pixel_id' => $s->facebook_pixel_id !== '' ? $s->facebook_pixel_id : $default,
                'facebook_access_token' => $s->facebook_access_token !== '' ? $s->facebook_access_token : $default,
                'facebook_test_event_code' => $s->facebook_test_event_code !== '' ? $s->facebook_test_event_code : $default,
                'tiktok_pixel_id' => $s->tiktok_pixel_id !== '' ? $s->tiktok_pixel_id : $default,
                'tiktok_access_token' => $s->tiktok_access_token !== '' ? $s->tiktok_access_token : $default,
                'tracking_debug' => $s->tracking_debug,
                default => $default,
            };
        });
    }
}

if (! function_exists('forget_setting_cache')) {
    function forget_setting_cache(): void
    {
        foreach ([
            'facebook_pixel_id',
            'facebook_access_token',
            'facebook_test_event_code',
            'tiktok_pixel_id',
            'tiktok_access_token',
            'tracking_debug',
        ] as $key) {
            Cache::forget('setting.cache.'.$key);
        }
    }
}
