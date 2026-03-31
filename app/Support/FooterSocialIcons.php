<?php

namespace App\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class FooterSocialIcons
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'globe' => 'موقع / رابط عام',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'twitter' => 'X / Twitter',
            'youtube' => 'YouTube',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'envelope' => 'بريد',
            'phone' => 'هاتف',
        ];
    }

    /**
     * @return array<string, Htmlable>
     */
    public static function toggleButtonIcons(): array
    {
        $icons = [];

        foreach (array_keys(self::options()) as $key) {
            $icons[$key] = new HtmlString(
                view('components.store.social-icon', [
                    'name' => $key,
                    'class' => 'h-5 w-5',
                ])->render()
            );
        }

        return $icons;
    }

    public static function normalizeKey(mixed $value): string
    {
        $keys = array_keys(self::options());

        if (is_string($value) && in_array($value, $keys, true)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $lower = Str::lower($value);
            $map = [
                'fab fa-facebook' => 'facebook',
                'facebook' => 'facebook',
                'fab fa-instagram' => 'instagram',
                'instagram' => 'instagram',
                'fab fa-twitter' => 'twitter',
                'twitter' => 'twitter',
                'fab fa-youtube' => 'youtube',
                'youtube' => 'youtube',
                'fab fa-linkedin' => 'linkedin',
                'linkedin' => 'linkedin',
                'fab fa-tiktok' => 'tiktok',
                'tiktok' => 'tiktok',
                'fab fa-whatsapp' => 'whatsapp',
                'whatsapp' => 'whatsapp',
                'fab fa-telegram' => 'telegram',
                'telegram' => 'telegram',
                'fa-envelope' => 'envelope',
                'envelope' => 'envelope',
                'fa-phone' => 'phone',
                'phone' => 'phone',
                'globe' => 'globe',
                'link' => 'globe',
            ];
            if (isset($map[$lower])) {
                return $map[$lower];
            }
        }

        return 'globe';
    }
}
