<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogMediaController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderInvoiceController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/catalog-media/{path}', [CatalogMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('catalog.media');

Route::get('/', [StoreController::class, 'index'])->name('store.home');
Route::get('/categories/{slug}', [StoreController::class, 'category'])->name('store.category');
Route::get('/products/{slug}', [StoreController::class, 'product'])->name('store.product');

Route::get('/cart', [CartController::class, 'index'])->name('store.cart');
Route::get('/cart/drawer', [CartController::class, 'drawer'])->name('store.cart.drawer');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('store.checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('store.checkout.store');
Route::post('/cart', [CartController::class, 'store'])->name('store.cart.add');
Route::post('/cart/shipping-zone', [CartController::class, 'setShippingZone'])->name('store.cart.shipping-zone');
Route::post('/cart/clear', [CartController::class, 'clear'])->name('store.cart.clear');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('store.cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('store.cart.remove');

Route::middleware('auth')->group(function () {
    Route::get('/invoices/orders/{order}', [OrderInvoiceController::class, 'show'])
        ->name('invoices.orders.show');
});
