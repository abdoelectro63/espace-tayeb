<?php

namespace App\Services\Invoice;

use App\Models\InvoiceSetting;
use App\Models\ManualInvoice;
use App\Models\Order;

final class InvoiceAmountCalculator
{
    /**
     * @return array{
     *   lines: list<array{designation: string, qty: int, unit_ht: float, montant_ht: float}>,
     *   net_commercial_ht: float,
     *   shipping_ht: float,
     *   total_ht: float,
     *   tva_rate: float,
     *   tva_amount: float,
     *   total_ttc: float,
     * }
     */
    public static function forManualInvoice(ManualInvoice $invoice): array
    {
        $invoice->loadMissing('lines');

        $tvaRate = (float) (InvoiceSetting::singleton()->default_tva_rate ?? 14);

        $lines = [];
        $netCommercial = 0.0;

        foreach ($invoice->lines as $item) {
            $qty = max(1, (int) $item->quantity);
            $unit = (float) $item->unit_price;
            $montant = round($qty * $unit, 2);
            $netCommercial += $montant;
            $lines[] = [
                'designation' => trim((string) $item->designation) !== '' ? trim((string) $item->designation) : 'Article',
                'qty' => $qty,
                'unit_ht' => $unit,
                'montant_ht' => $montant,
            ];
        }

        $totalHt = round($netCommercial, 2);
        $tvaAmount = round($totalHt * ($tvaRate / 100), 2);
        $totalTtc = round($totalHt + $tvaAmount, 2);

        return [
            'lines' => $lines,
            'net_commercial_ht' => round($netCommercial, 2),
            'shipping_ht' => 0.0,
            'total_ht' => $totalHt,
            'tva_rate' => $tvaRate,
            'tva_amount' => $tvaAmount,
            'total_ttc' => $totalTtc,
        ];
    }

    /**
     * @return array{
     *   lines: list<array{designation: string, qty: int, unit_ht: float, montant_ht: float}>,
     *   net_commercial_ht: float,
     *   shipping_ht: float,
     *   total_ht: float,
     *   tva_rate: float,
     *   tva_amount: float,
     *   total_ttc: float,
     * }
     */
    public static function forOrder(Order $order): array
    {
        $order->loadMissing(['orderItems.product', 'orderItems.productVariation']);

        $tvaRate = (float) (InvoiceSetting::singleton()->default_tva_rate ?? 14);

        $lines = [];
        $netCommercial = 0.0;

        foreach ($order->orderItems as $item) {
            $qty = max(1, (int) $item->quantity);
            $unit = (float) $item->unit_price;
            $montant = round($qty * $unit, 2);
            $netCommercial += $montant;
            $catalogName = $item->product?->name ?? 'Article';
            if ($item->productVariation) {
                $catalogName .= ' — '.$item->productVariation->label();
            }
            $designation = filled($item->invoice_designation)
                ? trim((string) $item->invoice_designation)
                : $catalogName;
            $lines[] = [
                'designation' => $designation,
                'catalog_name' => $catalogName,
                'qty' => $qty,
                'unit_ht' => $unit,
                'montant_ht' => $montant,
            ];
        }

        $totalHt = round($netCommercial, 2);
        $tvaAmount = round($totalHt * ($tvaRate / 100), 2);
        $totalTtc = round($totalHt + $tvaAmount, 2);

        return [
            'lines' => $lines,
            'net_commercial_ht' => round($netCommercial, 2),
            'shipping_ht' => 0.0,
            'total_ht' => $totalHt,
            'tva_rate' => $tvaRate,
            'tva_amount' => $tvaAmount,
            'total_ttc' => $totalTtc,
        ];
    }
}
