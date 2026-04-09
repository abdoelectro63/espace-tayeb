<?php

namespace App\Support\Tracking;

/**
 * Normalization for Meta / TikTok hashed user identifiers.
 */
final class TrackingHasher
{
    public static function hashEmail(?string $email): ?string
    {
        $email = $email !== null ? trim(mb_strtolower($email)) : '';

        return $email !== '' ? hash('sha256', $email) : null;
    }

    /**
     * Meta recommends digits only, with country code (no symbols).
     */
    public static function hashPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '212'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '212')) {
            $digits = '212'.$digits;
        }

        return hash('sha256', $digits);
    }
}
