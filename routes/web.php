<?php

use App\Http\Controllers\OrderInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/invoices/orders/{order}', [OrderInvoiceController::class, 'show'])
        ->name('invoices.orders.show');
});
