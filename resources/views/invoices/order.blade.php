<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $order->number }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: 700; }
        .meta { font-size: 14px; color: #6b7280; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #e5e7eb; text-align: left; padding: 10px 8px; font-size: 14px; }
        th { color: #374151; font-weight: 600; background: #f9fafb; }
        .actions { margin-top: 20px; display: flex; gap: 10px; }
        .btn { border: 1px solid #d1d5db; background: #ffffff; border-radius: 6px; padding: 10px 14px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #111827; color: #ffffff; border-color: #111827; }
        @media print { .actions { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="title">Order Invoice</div>
                <div class="meta">Order #: {{ $order->number }}</div>
            </div>
            <div class="meta">Printed at: {{ now()->format('Y-m-d H:i') }}</div>
        </div>

        <div class="card">
            <h3>Customer</h3>
            <table>
                <tbody>
                    <tr><th>Name</th><td>{{ $order->customer_name }}</td></tr>
                    <tr><th>Phone</th><td>{{ $order->customer_phone }}</td></tr>
                    <tr><th>Address</th><td>{{ $order->shipping_address }}</td></tr>
                    <tr><th>City</th><td>{{ $order->city }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>Order Payment</h3>
            <table>
                <tbody>
                    <tr><th>Status</th><td>{{ $order->status }}</td></tr>
                    <tr><th>Payment Status</th><td>{{ $order->payment_status ?? 'unpaid' }}</td></tr>
                    <tr><th>Paid At</th><td>{{ optional($order->paid_at)->format('Y-m-d H:i') ?? '-' }}</td></tr>
                    <tr><th>Total</th><td>{{ number_format((float) $order->total_price, 2) }} MAD</td></tr>
                </tbody>
            </table>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="window.print()">Print</button>
            <button class="btn" onclick="window.history.back()">Back</button>
        </div>
    </div>
</body>
</html>
