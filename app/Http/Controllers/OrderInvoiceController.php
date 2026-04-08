<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSetting;
use App\Models\Order;
use App\Services\Invoice\InvoiceAmountCalculator;
use App\Support\FrenchCurrencyInWords;
use Dompdf\Dompdf;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

class OrderInvoiceController extends Controller
{
    public function show(Order $order): View
    {
        $this->authorizeInvoice($order);

        return view('invoices.invoice', $this->invoiceViewData($order));
    }

    public function pdf(Order $order): Response
    {
        $this->authorizeInvoice($order);

        $html = view('invoices.invoice', $this->invoiceViewData($order))->render();

        $dompdf = new Dompdf([
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'facture-'.$order->number.'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function authorizeInvoice(Order $order): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'confirmation', 'manager'], true), 403);

        abort_unless(
            $order->payment_status === 'paid'
            && in_array($order->status, ['delivered', 'completed'], true),
            404
        );
    }

    /**
     * @return array{order: Order, settings: InvoiceSetting, amounts: array, totalInWords: string}
     */
    private function invoiceViewData(Order $order): array
    {
        $order->loadMissing([
            'orderItems.product',
            'orderItems.productVariation',
            'shippingCompany',
            'deliveryMan',
        ]);

        $settings = InvoiceSetting::singleton();
        $amounts = InvoiceAmountCalculator::forOrder($order);
        $totalInWords = FrenchCurrencyInWords::format($amounts['total_ttc']);

        return [
            'order' => $order,
            'settings' => $settings,
            'amounts' => $amounts,
            'totalInWords' => $totalInWords,
        ];
    }
}
