<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\ShippingInvoiceImport;
use App\Models\ShippingInvoiceImportLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ShippingInvoiceImporter
{
    /**
     * @param  'both'|'vitips'|'express'  $carrierFilter  Vitips Express (CL-…) vs Express Coursier (batch) — « vitips » ignore les blocs Express dans le fichier.
     * @return array{
     *     updated: int,
     *     skipped_zero_frais: int,
     *     not_found: int,
     *     unmatched_lines: int,
     *     vitips_parsed: int,
     *     express_parsed: int,
     *     ineligible_vitips: int,
     * }
     */
    public function process(string $rawText, string $carrierFilter = 'both'): array
    {
        if (! in_array($carrierFilter, ['both', 'vitips', 'express'], true)) {
            $carrierFilter = 'both';
        }

        $text = $this->normalizeText($rawText);

        $vitipsRows = in_array($carrierFilter, ['both', 'vitips'], true)
            ? collect($this->parseVitipsRows($rawText))
                ->unique('tracking')
                ->values()
                ->all()
            : [];

        $expressRows = in_array($carrierFilter, ['both', 'express'], true)
            ? collect($this->parseExpressBlocks($text))
                ->unique(fn (array $row): string => $row['colis_id'])
                ->values()
                ->all()
            : [];

        $updated = 0;
        $skippedZero = 0;
        $notFound = 0;
        $unmatched = 0;
        $ineligibleVitips = 0;
        $skippedAlreadyPaid = 0;

        DB::transaction(function () use ($vitipsRows, $expressRows, &$updated, &$skippedZero, &$notFound, &$unmatched, &$ineligibleVitips, &$skippedAlreadyPaid): void {
            $processedOrderIds = [];

            foreach ($vitipsRows as $row) {
                $anyOrder = $this->findOrderByTrackingAny($row['tracking']);
                if ($anyOrder !== null && $this->isOrderPaid($anyOrder)) {
                    $skippedAlreadyPaid++;

                    continue;
                }

                $order = $this->findShippedUnpaidOrderByTracking($row['tracking']);
                if ($order === null) {
                    if ($this->findOrderByTrackingAny($row['tracking']) !== null) {
                        $ineligibleVitips++;
                    } else {
                        $notFound++;
                    }

                    continue;
                }

                if (isset($processedOrderIds[$order->id])) {
                    continue;
                }

                if ($row['frais'] === 0) {
                    $skippedZero++;

                    continue;
                }

                $this->markDeliveredAndPaid($order);
                $processedOrderIds[$order->id] = true;
                $updated++;
            }

            foreach ($expressRows as $row) {
                $anyOrder = $this->findOrderByColisIdAny($row['colis_id']);
                if ($anyOrder !== null && $this->isOrderPaid($anyOrder)) {
                    $skippedAlreadyPaid++;

                    continue;
                }

                $order = $this->findShippedUnpaidOrderByColisId($row['colis_id']);
                if ($order === null) {
                    $unmatched++;

                    continue;
                }

                if (isset($processedOrderIds[$order->id])) {
                    continue;
                }

                if ($row['frais'] === 0) {
                    $skippedZero++;

                    continue;
                }

                $this->markDeliveredAndPaid($order);
                $processedOrderIds[$order->id] = true;
                $updated++;
            }
        });

        return [
            'updated' => $updated,
            'skipped_zero_frais' => $skippedZero,
            'not_found' => $notFound,
            'unmatched_lines' => $unmatched,
            'vitips_parsed' => count($vitipsRows),
            'express_parsed' => count($expressRows),
            'ineligible_vitips' => $ineligibleVitips,
            'skipped_already_paid' => $skippedAlreadyPaid,
        ];
    }

    /**
     * Build one row per parsed invoice line with order snapshots (for preview / persistence).
     *
     * @return list<array<string, mixed>>
     */
    public function analyzeForImport(string $rawText, string $carrierFilter = 'both'): array
    {
        if (! in_array($carrierFilter, ['both', 'vitips', 'express'], true)) {
            $carrierFilter = 'both';
        }

        $text = $this->normalizeText($rawText);

        $vitipsRows = in_array($carrierFilter, ['both', 'vitips'], true)
            ? collect($this->parseVitipsRows($rawText))
                ->unique('tracking')
                ->values()
                ->all()
            : [];

        $expressRows = in_array($carrierFilter, ['both', 'express'], true)
            ? collect($this->parseExpressBlocks($text))
                ->unique(fn (array $row): string => $row['colis_id'])
                ->values()
                ->all()
            : [];

        $lines = [];

        foreach ($vitipsRows as $row) {
            $anyOrder = $this->findOrderByTrackingAny($row['tracking']);
            if ($anyOrder !== null && $this->isOrderPaid($anyOrder)) {
                $lines[] = [
                    'carrier' => 'vitips',
                    'tracking_key' => $row['tracking'],
                    'order_id' => $anyOrder->id,
                    'customer_name' => $anyOrder->customer_name,
                    'city' => $anyOrder->city,
                    'total_price' => $anyOrder->total_price,
                    'invoice_frais' => $row['frais'],
                    'etat' => $row['etat'],
                    'match_status' => 'already_paid',
                ];

                continue;
            }

            $order = $this->findShippedUnpaidOrderByTracking($row['tracking']);
            $match = 'eligible';
            if ($order === null) {
                $match = $this->findOrderByTrackingAny($row['tracking']) !== null ? 'ineligible' : 'not_found';
            } elseif ($row['frais'] === 0) {
                $match = 'skipped_zero';
            }

            $lines[] = [
                'carrier' => 'vitips',
                'tracking_key' => $row['tracking'],
                'order_id' => $order?->id,
                'customer_name' => $order?->customer_name,
                'city' => $order?->city,
                'total_price' => $order?->total_price,
                'invoice_frais' => $row['frais'],
                'etat' => $row['etat'],
                'match_status' => $match,
            ];
        }

        foreach ($expressRows as $row) {
            $anyOrder = $this->findOrderByColisIdAny($row['colis_id']);
            if ($anyOrder !== null && $this->isOrderPaid($anyOrder)) {
                $lines[] = [
                    'carrier' => 'express',
                    'tracking_key' => $row['colis_id'],
                    'order_id' => $anyOrder->id,
                    'customer_name' => $anyOrder->customer_name,
                    'city' => $anyOrder->city,
                    'total_price' => $anyOrder->total_price,
                    'invoice_frais' => $row['frais'],
                    'etat' => null,
                    'match_status' => 'already_paid',
                ];

                continue;
            }

            $order = $this->findShippedUnpaidOrderByColisId($row['colis_id']);
            $match = 'eligible';
            if ($order === null) {
                $match = 'not_found';
            } elseif ($row['frais'] === 0) {
                $match = 'skipped_zero';
            }

            $lines[] = [
                'carrier' => 'express',
                'tracking_key' => $row['colis_id'],
                'order_id' => $order?->id,
                'customer_name' => $order?->customer_name,
                'city' => $order?->city,
                'total_price' => $order?->total_price,
                'invoice_frais' => $row['frais'],
                'etat' => null,
                'match_status' => $match,
            ];
        }

        return $lines;
    }

    /**
     * Mark eligible lines as collected: order delivered + paid, line collected.
     *
     * @param  'vitips'|'express'  $carrier
     */
    public function collectFundsForCarrier(ShippingInvoiceImport $import, string $carrier): int
    {
        if (! in_array($carrier, ['vitips', 'express'], true)) {
            return 0;
        }

        if ($import->lines()->count() === 0) {
            return 0;
        }

        $updated = 0;

        DB::transaction(function () use ($import, $carrier, &$updated): void {
            /** @var Collection<int, ShippingInvoiceImportLine> $lines */
            $lines = ShippingInvoiceImportLine::query()
                ->where('shipping_invoice_import_id', $import->id)
                ->where('carrier', $carrier)
                ->where('match_status', 'eligible')
                ->whereNull('collected_at')
                ->get();

            foreach ($lines as $line) {
                $order = $line->order_id !== null ? Order::query()->find($line->order_id) : null;
                if ($order === null) {
                    continue;
                }

                if ($this->isOrderPaid($order)) {
                    continue;
                }

                $this->markDeliveredAndPaid($order);
                $line->forceFill([
                    'collected_at' => now(),
                    'match_status' => 'collected',
                ])->save();
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * @return list<array{tracking: string, frais: int, etat: string}>
     */
    public function parseVitipsRows(string $text): array
    {
        $text = $this->normalizeVitipsText($text);

        // Avoid \b around "CL-…": word boundaries behave badly with "-" in some PDF extractions.
        if ($text === '' || ! preg_match_all('/(?<![A-Za-z0-9])(CL-\d{8,14})(?![0-9])/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $rows = [];
        $trackCount = count($matches[1]);

        for ($i = 0; $i < $trackCount; $i++) {
            $tracking = $matches[1][$i][0];
            $offset = (int) $matches[1][$i][1];
            $endOffset = ($i + 1 < $trackCount) ? (int) $matches[1][$i + 1][1] : strlen($text);
            $chunk = substr($text, $offset, max(0, $endOffset - $offset));

            $parsed = $this->parseVitipsChunkAmounts($chunk);
            if ($parsed === null) {
                continue;
            }

            $rows[] = [
                'tracking' => $tracking,
                'etat' => $parsed['etat'],
                'frais' => $parsed['frais'],
            ];
        }

        return $rows;
    }

    /**
     * PDF exports often omit line breaks or split "Livré" across tokens — parse per CL-… chunk.
     *
     * @return array{etat: string, frais: int}|null
     */
    private function parseVitipsChunkAmounts(string $chunk): ?array
    {
        $etatReturn = '(?:Retour\s+r[ée]ceptionn[ée])';
        $etatLivre = '(?:Liv\s*r[ée]|Livr[ée]|Livre|Livré)';

        if (preg_match(
            '/'.$etatReturn.'\s+(\d+)\s*DH\s+(\d+)\s*DH/iu',
            $chunk,
            $m
        )) {
            return [
                'etat' => 'Retour réceptionné',
                'frais' => (int) $m[2],
            ];
        }

        if (preg_match(
            '/'.$etatLivre.'\s+(\d+)\s*DH\s+(\d+)\s*DH/iu',
            $chunk,
            $m
        )) {
            return [
                'etat' => 'Livré',
                'frais' => (int) $m[2],
            ];
        }

        if (preg_match_all('/(\d+)\s*DH\s+(\d+)\s*DH/iu', $chunk, $pairs, PREG_SET_ORDER)) {
            $last = $pairs[array_key_last($pairs)];

            return [
                'etat' => 'Livré',
                'frais' => (int) $last[2],
            ];
        }

        return null;
    }

    /**
     * Flatten PDF quirks: NBSP, unicode newlines, split lines so CL- rows are searchable.
     * Many Vitips PDFs split "C", "L", "-", digits or use fullwidth digits — rebuild "CL-##########".
     */
    private function normalizeVitipsText(string $text): string
    {
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/[\x{2000}-\x{200B}\x{FEFF}]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R+/u', ' ', $text) ?? $text;

        if (extension_loaded('intl') && class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            if ($normalized !== false) {
                $text = $normalized;
            }
        }

        $text = $this->normalizeDigitsToWesternAscii($text);

        // Unicode dashes → ASCII hyphen (before rebuilding CL tokens)
        $text = preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u', '-', $text) ?? $text;

        $text = $this->reconstructVitipsClTokens($text);

        $text = preg_replace('/\h+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function normalizeDigitsToWesternAscii(string $text): string
    {
        static $map = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];

        return strtr($text, $map);
    }

    /**
     * Rebuild standard Vitips tokens so /CL-\d+/ matches (PDFs often break "CL-" apart).
     */
    private function reconstructVitipsClTokens(string $text): string
    {
        // "CL - 123" or "CL- 123" → "CL-123"
        $text = preg_replace('/(?i)(?<![A-Za-z0-9])CL\s*-\s*(\d{8,14})(?!\d)/u', 'CL-$1', $text) ?? $text;

        // "CL123" without hyphen (digits glued to CL)
        $text = preg_replace('/(?i)(?<![A-Za-z0-9])CL(\d{8,14})(?!\d)/u', 'CL-$1', $text) ?? $text;

        // "C L - 123" / "C L – 123"
        $text = preg_replace(
            '/(?<![A-Za-z0-9])C\s+L\s*-\s*(\d{8,14})(?!\d)/u',
            'CL-$1',
            $text
        ) ?? $text;

        // "C L" + punctuation dash + digits (covers odd PDF fonts)
        $text = preg_replace(
            '/(?<![A-Za-z0-9])C\s+L\s*\p{Pd}\s*(\d{8,14})(?!\d)/u',
            'CL-$1',
            $text
        ) ?? $text;

        // "CL 1234567890" (space instead of hyphen)
        $text = preg_replace(
            '/(?i)(?<![A-Za-z0-9])CL\s+(\d{8,14})(?!\d)/u',
            'CL-$1',
            $text
        ) ?? $text;

        // lowercase cl-
        $text = preg_replace('/(?i)(?<![A-Za-z0-9])cl-(\d{8,14})(?!\d)/u', 'CL-$1', $text) ?? $text;

        return $text;
    }

    /**
     * Express Coursier:
     * - Long id: 2603161519-553C3427787140 (DB often CLA-…)
     * - Numeric only: 2020002555 on its own line (DB often CL-2020002555)
     *
     * @return list<array{colis_id: string, frais: int}>
     */
    public function parseExpressBlocks(string $text): array
    {
        $rows = array_merge(
            $this->parseExpressLongColisBlocks($text),
            $this->parseExpressNumericColisBlocks($text),
        );

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $id = $row['colis_id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<array{colis_id: string, frais: int}>
     */
    private function parseExpressLongColisBlocks(string $text): array
    {
        $rows = [];
        $pattern = '/(\d{10}-\d{3}C\d+)(.*?)(?=\d{10}-\d{3}C\d+|\z)/s';

        if (! preg_match_all($pattern, $text, $blockMatches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($blockMatches as $blockMatch) {
            $colisId = $blockMatch[1];
            $block = $blockMatch[2] ?? '';

            if (! str_contains($block, 'Livré:')) {
                continue;
            }

            $frais = $this->extractExpressFraisFromBlock($block);
            if ($frais === null) {
                continue;
            }

            $rows[] = [
                'colis_id' => $colisId,
                'frais' => $frais,
            ];
        }

        return $rows;
    }

    /**
     * Blocks where colis is only digits on one line, then "Livré:" (invoice ≠ Vitips "Livré … DH").
     * Excludes 0XXXXXXXXX (Moroccan phones) to reduce false positives.
     *
     * @return list<array{colis_id: string, frais: int}>
     */
    private function parseExpressNumericColisBlocks(string $text): array
    {
        $text = preg_replace('/\R+/u', "\n", $text) ?? $text;
        $rows = [];

        if (! preg_match_all(
            '/(?m)^(?!0\d{9}$)(\d{8,12})\s*$\s*\n\s*Livré:/u',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return [];
        }

        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $fullMatch = $matches[0][$i];
            $offset = (int) $fullMatch[1];
            $colisId = $matches[1][$i][0];
            $nextOffset = ($i + 1 < $count) ? (int) $matches[0][$i + 1][1] : strlen($text);
            $block = substr($text, $offset, max(0, $nextOffset - $offset));

            $frais = $this->extractExpressFraisFromBlock($block);
            if ($frais === null) {
                continue;
            }

            $rows[] = [
                'colis_id' => $colisId,
                'frais' => $frais,
            ];
        }

        return $rows;
    }

    private function extractExpressFraisFromBlock(string $block): ?int
    {
        if (preg_match_all(
            '/^(\d{9,10})\s+([\d.]+)\s+MAD\s+(.+)\s+(\d+)\s+MAD\s*$/mu',
            $block,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                if ((int) $m[4] >= 0) {
                    return (int) $m[4];
                }
            }
        }

        return null;
    }

    private function normalizeText(string $raw): string
    {
        $raw = str_replace("\xC2\xA0", ' ', $raw);

        return $raw;
    }

    private function shippedUnpaidQuery(): Builder
    {
        return Order::query()
            ->where('status', 'shipped')
            ->whereNotNull('shipping_company')
            ->where('shipping_company', '!=', '')
            ->where(function ($q): void {
                $q
                    ->whereNull('payment_status')
                    ->orWhere('payment_status', '!=', 'paid');
            });
    }

    /**
     * @return list<string>
     */
    private function trackingLookupVariants(string $tracking): array
    {
        $t = trim($tracking);
        $ascii = preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u', '-', $t) ?? $t;
        $variants = array_unique(array_filter([
            $t,
            $ascii,
            str_replace('-', "\u{2011}", $ascii), // non-breaking hyphen
            str_replace('-', "\u{2010}", $ascii), // hyphen
            str_replace('-', "\u{2212}", $ascii), // minus sign
        ]));

        return array_values($variants);
    }

    private function findShippedUnpaidOrderByTracking(string $tracking): ?Order
    {
        return $this->shippedUnpaidQuery()
            ->whereIn('tracking_number', $this->trackingLookupVariants($tracking))
            ->first();
    }

    private function findOrderByTrackingAny(string $tracking): ?Order
    {
        return Order::query()
            ->whereIn('tracking_number', $this->trackingLookupVariants($tracking))
            ->first();
    }

    private function findShippedUnpaidOrderByColisId(string $colisId): ?Order
    {
        if ($colisId === '') {
            return null;
        }

        return $this->shippedUnpaidQuery()
            ->whereNotNull('tracking_number')
            ->where(function ($q) use ($colisId): void {
                $q
                    ->where('tracking_number', 'like', '%'.$colisId.'%')
                    ->orWhere('tracking_number', 'CL-'.$colisId)
                    ->orWhere('tracking_number', 'CLA-'.$colisId);
            })
            ->first();
    }

    private function findOrderByColisIdAny(string $colisId): ?Order
    {
        if ($colisId === '') {
            return null;
        }

        return Order::query()
            ->whereNotNull('tracking_number')
            ->where(function ($q) use ($colisId): void {
                $q
                    ->where('tracking_number', 'like', '%'.$colisId.'%')
                    ->orWhere('tracking_number', 'CL-'.$colisId)
                    ->orWhere('tracking_number', 'CLA-'.$colisId);
            })
            ->first();
    }

    private function isOrderPaid(Order $order): bool
    {
        return $order->payment_status === 'paid';
    }

    private function markDeliveredAndPaid(Order $order): void
    {
        $order->forceFill([
            'status' => 'delivered',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ])->save();
    }
}
