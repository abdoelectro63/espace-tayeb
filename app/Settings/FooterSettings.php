<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FooterSettings extends Settings
{
    public string $copyright_text;

    public string $tagline;

    public array $social_links;

    public static function group(): string
    {
        return 'footer';
    }
}
