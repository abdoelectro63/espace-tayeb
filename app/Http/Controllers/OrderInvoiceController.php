<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

class OrderInvoiceController extends Controller
{
    public function show(Order $order): View
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'confirmation'], true), 403);

        return view('invoices.order', [
            'order' => $order,
        ]);
    }
}
