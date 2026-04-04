<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /** Max raw upload size before decode (bytes). Large PNGs use a lot of memory when decoded. */
    public const int MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    public const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * @param  string  $directory  Path segment under the public disk, e.g. `products/titles`, `categories/images` (no leading slash).
     * @param  string  $validationKey  Filament/Livewire field name for ValidationException mapping.
     */
    public static function processAndStore(TemporaryUploadedFile $file, string $directory, string $validationKey = 'file'): string
    {
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            throw ValidationException::withMessages([
                $validationKey => [__('The server needs the GD or Imagick PHP extension to process images.')],
            ]);
        }

        $size = (int) $file->getSize();
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages([
                $validationKey => [__('Image is too large before processing (max :max MB). Use a smaller file or stronger compression.', ['max' => (int) (self::MAX_UPLOAD_BYTES / 1024 / 1024)])],
            ]);
        }

        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        if ($mime === '' || ! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                $validationKey => [__('Use JPG, PNG, WebP, or GIF. AVIF/HEIC and other formats are not supported here.')],
            ]);
        }

        $directory = trim($directory, '/');

        $relativePath = sprintf('%s/%s.webp', $directory, Str::uuid()->toString());

        $source = $file->getRealPath() ?: $file->getPathname();

        Log::info('ImageOptimizer: processing admin upload', [
            'bytes' => $size,
            'mime' => $mime,
            'target' => $relativePath,
        ]);

        @set_time_limit(120);
        $prevMemory = ini_get('memory_limit');
        if ($prevMemory !== false) {
            @ini_set('memory_limit', '512M');
        }

        try {
            $encoded = Image::read($source)
                ->scaleDown(self::MAX_WIDTH)
                ->toWebp(self::WEBP_QUALITY);
        } catch (Throwable $e) {
            report($e);
            Log::warning('ImageOptimizer: read/encode failed', [
                'mime' => $mime,
                'bytes' => $size,
                'message' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $validationKey => [__('Could not read or convert this image. Try JPG or PNG under :max MB.', ['max' => (int) (self::MAX_UPLOAD_BYTES / 1024 / 1024)])],
            ]);
        } finally {
            if ($prevMemory !== false) {
                @ini_set('memory_limit', (string) $prevMemory);
            }
        }

        $written = Storage::disk('public')->put($relativePath, (string) $encoded, 'public');
        if ($written === false) {
            Log::error('ImageOptimizer: public disk put failed', ['path' => $relativePath]);

            throw ValidationException::withMessages([
                $validationKey => [__('Could not save the image. For Cloudflare R2 set FILESYSTEM_PUBLIC_DRIVER=s3 and verify AWS_* in .env.')],
            ]);
        }

        Log::info('ImageOptimizer: stored webp', ['path' => $relativePath]);

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
