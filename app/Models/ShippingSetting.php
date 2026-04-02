<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShippingSetting extends Model
{
    protected $fillable = [
        'casablanca_fee',
        'other_cities_fee',
        'logo_path',
        'header_bg_color',
        'menu_text_color',
        'hero_banner_path',
        'hero_banner_link',
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
            'logo_path' => null,
            'header_bg_color' => '#ffffff',
            'menu_text_color' => '#0f172a',
            'hero_banner_path' => null,
            'hero_banner_link' => null,
        ]);
    }

    /**
     * Public URL for the storefront logo (uploaded branding or default SVG).
     */
    public static function storeLogoUrl(): string
    {
        $branding = static::query()->first();
        $logoPath = $branding?->logo_path;
        $logoUrl = asset('images/logo.svg');

        if (filled($logoPath)) {
            $disk = Storage::disk('public');
            $logoUrl = $disk->url($logoPath);

            if ($disk->exists($logoPath)) {
                $logoUrl .= '?v='.$disk->lastModified($logoPath);
            }
        }

        return $logoUrl;
    }

    /**
     * MIME type for {@see storeLogoUrl()} when used as a favicon (path extension, query stripped).
     */
    public static function faviconMimeTypeForLogoUrl(string $logoUrl): string
    {
        $path = parse_url($logoUrl, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            default => 'image/svg+xml',
        };
    }
}
