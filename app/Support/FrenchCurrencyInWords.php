<?php

namespace App\Support;

use NumberFormatter;

/**
 * Convert an amount to French words for invoices (montant en lettres).
 */
final class FrenchCurrencyInWords
{
    public static function format(float $amount): string
    {
        $rounded = round($amount, 2);
        $dirhams = (int) floor($rounded);
        $centimes = (int) round(($rounded - $dirhams) * 100);

        if (extension_loaded('intl')) {
            $fmt = new NumberFormatter('fr_FR', NumberFormatter::SPELLOUT);

            $d = $fmt->format($dirhams);
            $base = $d.' DHS';
            if ($centimes > 0) {
                $c = $fmt->format($centimes);

                return $base.' et '.$c.($centimes === 1 ? ' centime' : ' centimes');
            }

            return $base;
        }

        return number_format($rounded, 2, ',', ' ').' DHS';
    }
}
