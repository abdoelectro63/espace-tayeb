<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $order->number }}</title>
    <style>
        @page { size: A4; margin: 10px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            direction: ltr;
            text-align: left;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            padding: 6px;
        }

        .no-break {
            page-break-inside: avoid;
        }

        .header-top {
            text-align: left;
            vertical-align: top;
        }

        .header-top h2 {
            margin: 10px 0 6px 0;
            font-size: 18px;
        }

        .client-wrap {
            width: 50%;
            text-align: left;
        }

        .client-wrap td {
            border: none;
            vertical-align: top;
            text-align: left;
        }

        /* --- Main Items Table Configuration --- */
        table.items-main {
            width: 100%;
            border: 2px solid #000; /* Outer frame remains thick */
            border-collapse: collapse;
        }

        table.items-main th {
            background: #000;
            color: #fff;
            font-weight: bold;
            text-align: left;
            border: 1px solid #000; /* Borders for header cells */
        }

        table.items-main td {
            vertical-align: top;
            /* Vertical borders ON, Horizontal borders OFF */
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: none; 
        }

        /* Create the tall empty columns to fill the page height */
        .items-spacer td {
            height: 95mm;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: none;
        }

        table.items-main th.right,
        table.items-main td.right {
            text-align: right;
        }

        .right {
            text-align: right;
        }

        /* --- Summary Grid Configuration --- */
        table.summary-grid {
            width: 60%;
            margin-left: auto;
            border: 1px solid #000;
            border-collapse: collapse;
            font-size: 11px;
        }

        table.summary-grid th,
        table.summary-grid td {
            border: 1px solid #000;
            padding: 5px 8px;
        }

        table.summary-grid th {
            font-weight: bold;
            text-align: center;
        }

        .bottom-row td {
            vertical-align: top;
        }

        .arrete-block {
            text-align: left;
            padding-right: 12px;
        }

        .seller-legal {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            line-height: 1.6;
            margin-top: 20px;
            padding: 12px 8px;
        }
        /* Update this so it doesn't fight the container table */
        .client-wrap {
            width: 100%;
            text-align: left;
        }

        /* Ensure padding doesn't push the boxes out of alignment */
        .header-top, .client-wrap td {
            padding: 0;
            vertical-align: top;
        }

    </style>
</head>
<body>
@php
    $sellerName = filled($settings->seller_company_name) ? $settings->seller_company_name : 'Livreno Sarl';
    $invoiceDate = $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? '';
    $total = $amounts['total_ht'];
    $tva = $amounts['tva_amount'];
    $totalTTC = $amounts['total_ttc'];
    $totalWords = $totalInWords;
    $tvaRateLabel = number_format((float) $amounts['tva_rate'], 2, ',', ' ');
@endphp

<table width="100%" class="no-break" style="margin-bottom: 20px;">
    <tr>
        <td width="50%" valign="top">
            <table class="header-table">
                <tr>
                    <td class="header-top" style="padding: 0;">
                        <strong style="font-size: 20px;">{{ $sellerName }}</strong>
                        <h2 style="margin-top: 5px;">FACTURE</h2>
                        N° : {{ $order->number }}<br>
                        Date : {{ $invoiceDate }}
                    </td>
                </tr>
            </table>
        </td>

        <td width="50%" valign="top"">
            <table class="client-wrap" style="width: 100%; border: 1px solid #000;">
                <tr>
                    <td style="padding: 5;">
                        <strong>FACTURÉ À</strong><br>
                        @if(filled($order->invoice_client_company_name))
                            <strong>{{ $order->invoice_client_company_name }}</strong><br>
                        @endif
                        @if(filled($order->invoice_client_ice))
                            ICE : {{ $order->invoice_client_ice }}<br>
                        @endif
                        @if(filled($order->invoice_client_if))
                            I.F. : {{ $order->invoice_client_if }}<br>
                        @endif
                        @if(filled($order->invoice_client_rc))
                            RC : {{ $order->invoice_client_rc }}<br>
                        @endif
                        @if(filled($order->invoice_billing_address))
                            {!! nl2br(e($order->invoice_billing_address)) !!}
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<br>

<table class="items-main no-break">
    <thead>
    <tr>
        <th width="10%">QTE</th>
        <th width="46%">DESIGNATION</th>
        <th width="22%" class="right">PRIX UNIT HT</th>
        <th width="22%" class="right">MONTANT HT</th>
    </tr>
    </thead>
    <tbody>
    @foreach($amounts['lines'] as $i => $item)
        <tr>
            <td>{{ $item['qty'] }}</td>
            <td>{{ $item['designation'] }}</td>
            <td class="right">{{ number_format($item['unit_ht'], 2, ',', ' ') }} DHS</td>
            <td class="right">{{ number_format($item['montant_ht'], 2, ',', ' ') }} DHS</td>
        </tr>
    @endforeach
    
    <tr class="items-spacer">
        <td></td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
    </tbody>
</table>

<br>

<table class="no-break" width="100%">
    <tr class="bottom-row">
        <td width="60%" class="arrete-block">
            Arrêté la présente facture à la somme TTC de :<br><br>
            <strong>{{ $totalWords }}</strong>
        </td>
        <td width="52%" valign="top" align="right">
            <table class="summary-grid">
                <thead>
                <tr>
                    <th>Tx TVA</th>
                    <th>Mnt HT</th>
                    <th>Mnt TVA</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>{{ $tvaRateLabel }} %</td>
                    <td class="right">{{ number_format($total, 2, ',', ' ') }} DHS</td>
                    <td class="right">{{ number_format($tva, 2, ',', ' ') }} DHS</td>
                </tr>
                <tr>
                    <td><strong>Total :</strong></td>
                    <td class="right">{{ number_format($total, 2, ',', ' ') }} DHS</td>
                    <td class="right">{{ number_format($tva, 2, ',', ' ') }} DHS</td>
                </tr>
                <tr>
                    <td ><strong>Total TTC :</strong></td>
                    <td colspan="2" class="right"><strong style="font-size: 16px;">{{ number_format($totalTTC, 2, ',', ' ') }} DHS</strong></td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>

@if(filled($settings->seller_company_name) || filled($settings->seller_ice) || filled($settings->seller_rc) || filled($settings->seller_if) || filled($settings->seller_patente) || filled($settings->seller_rib))
    <div class="no-break seller-legal">
        @if(filled($settings->seller_company_name))
            {{ $settings->seller_company_name }}<br>
        @endif
        @if(filled($settings->seller_ice))
            ICE {{ $settings->seller_ice }}
        @endif
        @if(filled($settings->seller_rc))
            &nbsp;—&nbsp;RC {{ $settings->seller_rc }}
        @endif
        @if(filled($settings->seller_if))
            &nbsp;—&nbsp;IF {{ $settings->seller_if }}
        @endif
        @if(filled($settings->seller_patente))
            &nbsp;—&nbsp;Patente {{ $settings->seller_patente }}
        @endif
        @if(filled($settings->seller_rib))
            &nbsp;—&nbsp;RIB {{ $settings->seller_rib }}
        @endif
    </div>
@endif

</body>
</html>