<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSetting;
use App\Models\ManualInvoice;
use App\Services\Invoice\InvoiceAmountCalculator;
use App\Support\FrenchCurrencyInWords;
use Dompdf\Dompdf;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

class ManualInvoiceController extends Controller
{
    public function show(ManualInvoice $manualInvoice): View
    {
        $this->authorizeManualInvoice();

        return view('invoices.invoice', $this->invoiceViewData($manualInvoice));
    }

    public function pdf(ManualInvoice $manualInvoice): Response
    {
        $this->authorizeManualInvoice();

        $html = view('invoices.invoice', $this->invoiceViewData($manualInvoice))->render();

        $dompdf = new Dompdf([
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'facture-manuelle-'.($manualInvoice->number ?? $manualInvoice->id).'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function authorizeManualInvoice(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceViewData(ManualInvoice $manualInvoice): array
    {
        $manualInvoice->loadMissing('lines');

        $settings = InvoiceSetting::singleton();
        $amounts = InvoiceAmountCalculator::forManualInvoice($manualInvoice);
        $totalInWords = FrenchCurrencyInWords::format($amounts['total_ttc']);

        return [
            'manualInvoice' => $manualInvoice,
            'settings' => $settings,
            'amounts' => $amounts,
            'totalInWords' => $totalInWords,
        ];
    }
}
