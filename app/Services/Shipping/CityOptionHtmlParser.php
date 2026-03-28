<?php

namespace App\Services\Shipping;

/**
 * Parses carrier city dropdown HTML: Express-style &lt;a class="city-option"&gt; and Vitips-style &lt;option&gt;.
 */
final class CityOptionHtmlParser
{
    /**
     * Vitips (and many &lt;select&gt; widgets) use &lt;option value="…"&gt;Label&lt;/option&gt;.
     * Submitted value is usually `value`; visible text may differ.
     *
     * @return list<array{value: string, text: string}>
     */
    public static function parseSelectOptions(string $html): array
    {
        $html = (string) preg_replace('/^\xEF\xBB\xBF/', '', $html);
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);

        $rows = [];

        preg_match_all('/<option\b([^>]*)>(.*?)<\/option>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $attrs = $m[1];
            $inner = $m[2];

            if (preg_match('/\bdisabled\b/i', $attrs)) {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', self::decodeHtmlText(strip_tags($inner))));

            $value = '';
            if (preg_match('/\bvalue\s*=\s*(["\'])(.*?)\1/si', $attrs, $vm)) {
                $value = trim(self::decodeHtmlText($vm[2]));
            }

            if ($value === '' && $text === '') {
                continue;
            }

            if ($value === '' && self::looksLikeSelectPlaceholder($text)) {
                continue;
            }

            $rows[] = [
                'value' => $value,
                'text' => $text,
            ];
        }

        return $rows;
    }

    private static function looksLikeSelectPlaceholder(string $text): bool
    {
        $t = trim($text);
        if ($t === '' || mb_strlen($t) <= 1) {
            return true;
        }

        if (preg_match('/^(اختر|اختيار|المدينة|مدينة|ville|city|choose|select|—|-+|\.{3})$/iu', $t)) {
            return true;
        }

        if (preg_match('/^(اختر|اختيار)\s/u', $t)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<array{data_value: string, data_name: string, label: string}>
     */
    public static function parseAnchors(string $html): array
    {
        $html = (string) preg_replace('/^\xEF\xBB\xBF/', '', $html);
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);

        $rows = [];

        preg_match_all('/<a\b([^>]+)>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $attrs = $m[1];
            $inner = $m[2];

            if (! preg_match('/\bcity-option\b/i', $attrs)) {
                continue;
            }

            $dataValue = '';
            if (preg_match('/\bdata-value\s*=\s*(["\'])(.*?)\1/si', $attrs, $dv)) {
                $dataValue = self::decodeHtmlText(trim($dv[2]));
            }

            $dataName = '';
            if (preg_match('/\bdata-name\s*=\s*(["\'])(.*?)\1/si', $attrs, $dn)) {
                $dataName = self::decodeHtmlText(trim($dn[2]));
            }

            $label = trim(preg_replace('/\s+/u', ' ', self::decodeHtmlText(strip_tags($inner))));

            $rows[] = [
                'data_value' => $dataValue,
                'data_name' => $dataName,
                'label' => $label,
            ];
        }

        return $rows;
    }

    public static function decodeHtmlText(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
