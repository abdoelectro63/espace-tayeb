<?php

namespace App\Services\Shipping;

use App\Models\ShippingCompany;
use App\Models\ShippingCompanyCity;

class ShippingCompanyCityMatcher
{
    /**
     * Normalize free-text city for comparison (matches ShippingManager-style trimming).
     */
    public static function normalize(string $city): string
    {
        $city = trim(preg_replace('/\s+/u', ' ', $city));
        if ($city === '') {
            return '';
        }

        if (preg_match('/^(.+?)\s*\([^)]+\)\s*$/u', $city, $m)) {
            $city = trim($m[1]);
        }

        return mb_strtolower($city, 'UTF-8');
    }

    public function findMatchingCity(ShippingCompany $company, string $orderCity): ?ShippingCompanyCity
    {
        $normalized = self::normalize($orderCity);
        if ($normalized === '') {
            return null;
        }

        $cities = $company->cities()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $orderTrim = trim($orderCity);

        foreach ($cities as $city) {
            if (filled($city->vitips_city_id) && $orderTrim === trim((string) $city->vitips_city_id)) {
                return $city;
            }

            if (self::normalize($city->name) === $normalized) {
                return $city;
            }

            if (filled($city->vitips_label) && self::normalize((string) $city->vitips_label) === $normalized) {
                return $city;
            }

            foreach ($city->aliases ?? [] as $alias) {
                if (! is_string($alias) || $alias === '') {
                    continue;
                }
                if (self::normalize($alias) === $normalized) {
                    return $city;
                }
            }
        }

        return null;
    }
}
