<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Intervention Image v3: WebP output, max width, quality 80, unique filenames.
 *
 * Requires GD or Imagick (see config/image.php when published).
 */
final class ImageOptimizer
{
    public const int MAX_WIDTH = 1000;

    public const int WEBP_QUALITY = 80;

    /**
     * @param  string  $directory  Path segment under the public disk, e.g. `products/titles`, `categories/images` (no leading slash).
     */
    public static function processAndStore(TemporaryUploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');

        $relativePath = sprintf('%s/%s.webp', $directory, Str::uuid()->toString());

        $source = $file->getRealPath() ?: $file->getPathname();

        $encoded = Image::read($source)
            ->scaleDown(self::MAX_WIDTH)
            ->toWebp(self::WEBP_QUALITY);

        Storage::disk('public')->put($relativePath, (string) $encoded, 'public');

        return $relativePath;
    }

    /**
     * Download remote image URL, optimize and store as WebP.
     */
    public static function processRemoteImageUrl(string $url, string $directory): ?string
    {
        $directory = trim($directory, '/');
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $response = Http::timeout(12)->get($url);
        if (! $response->ok()) {
            return null;
        }

        $relativePath = sprintf('%s/%s.webp', $directory, Str::uuid()->toString());

        $encoded = Image::read($response->body())
            ->scaleDown(self::MAX_WIDTH)
            ->toWebp(self::WEBP_QUALITY);

        Storage::disk('public')->put($relativePath, (string) $encoded, 'public');

        return $relativePath;
    }
}
