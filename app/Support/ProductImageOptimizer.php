<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Optimizes product uploads: WebP output, max width, quality 80.
 *
 * Requires a supported Intervention driver (GD or Imagick) — both are enabled on Laravel Herd by default.
 *
 * @see https://image.intervention.io/v3/basics/configuration-drivers
 */
final class ProductImageOptimizer
{
    public const int MAX_WIDTH = 1000;

    public const int WEBP_QUALITY = 80;

    /**
     * @param  'titles'|'gallery'  $folder  Relative folder under `storage/app/public/products/`.
     */
    public static function processAndStore(TemporaryUploadedFile $file, string $folder): string
    {
        $folder = $folder === 'gallery' ? 'gallery' : 'titles';

        $relativePath = sprintf('products/%s/%s.webp', $folder, Str::uuid()->toString());

        $source = $file->getRealPath() ?: $file->getPathname();

        $encoded = Image::read($source)
            ->scaleDown(self::MAX_WIDTH)
            ->toWebp(self::WEBP_QUALITY);

        Storage::disk('public')->put($relativePath, (string) $encoded, 'public');

        return $relativePath;
    }
}
