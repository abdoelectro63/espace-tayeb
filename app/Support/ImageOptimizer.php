<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

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

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        try {
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
        } catch (Throwable) {
            // Never crash imports when an image URL is unreachable/invalid.
            return null;
        }
    }

    /**
     * Full URL (download) or a filename/path relative to the public disk (e.g. imports for CSV assets).
     */
    public static function processRemoteOrStoredPublicImage(string $value, string $directory): ?string
    {
        $directory = trim($directory, '/');
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if ($scheme === 'file') {
            $rawPath = (string) parse_url($value, PHP_URL_PATH);
            $rawPath = rawurldecode($rawPath);
            // Windows file URLs often look like /C:/path/to/file.jpg
            if (preg_match('#^/[A-Za-z]:/#', $rawPath) === 1) {
                $rawPath = ltrim($rawPath, '/');
            }

            $localPath = str_replace('/', DIRECTORY_SEPARATOR, $rawPath);
            if (is_readable($localPath)) {
                try {
                    $relativePath = sprintf('%s/%s.webp', $directory, Str::uuid()->toString());
                    $encoded = Image::read($localPath)
                        ->scaleDown(self::MAX_WIDTH)
                        ->toWebp(self::WEBP_QUALITY);

                    Storage::disk('public')->put($relativePath, (string) $encoded, 'public');

                    return $relativePath;
                } catch (Throwable) {
                    return null;
                }
            }
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return self::processRemoteImageUrl($value, $directory);
        }

        $normalized = ltrim(str_replace('\\', '/', $value), '/');
        foreach ([$normalized, 'imports/'.$normalized] as $relative) {
            if (! Storage::disk('public')->exists($relative)) {
                continue;
            }
            $absolute = Storage::disk('public')->path($relative);
            if (! is_readable($absolute)) {
                continue;
            }

            $relativePath = sprintf('%s/%s.webp', $directory, Str::uuid()->toString());
            $encoded = Image::read($absolute)
                ->scaleDown(self::MAX_WIDTH)
                ->toWebp(self::WEBP_QUALITY);

            Storage::disk('public')->put($relativePath, (string) $encoded, 'public');

            return $relativePath;
        }

        return null;
    }
}
