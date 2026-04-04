<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes objects from the `public` disk (local or S3/R2) only under known app prefixes.
 */
final class PublicDiskFileCleanup
{
    /** @var list<string> */
    private const PREFIXES = [
        'products/titles/',
        'products/gallery/',
        'categories/images/',
        'branding/',
        'pages/sections/',
    ];

    public static function filamentDeleteUploadedFile(): Closure
    {
        return static function (mixed $file): void {
            if (is_string($file) && $file !== '') {
                self::deletePathIfDeletable($file);
            }
        };
    }

    public static function deletePathIfDeletable(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $path = ltrim(trim($path), '/');

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return;
        }

        if (! self::isDeletableKey($path)) {
            return;
        }

        try {
            $disk = Storage::disk('public');
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return list<string>
     */
    public static function normalizeToPathList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn ($p) => is_string($p) ? trim($p) : '', $value)));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded)
                ? array_values(array_filter(array_map(static fn ($p) => is_string($p) ? trim($p) : '', $decoded)))
                : [];
        }

        return [];
    }

    private static function isDeletableKey(string $path): bool
    {
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
