<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
    Route::patch('/orders/{order}', [OrderController::class, 'update'])->name('api.orders.update');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('api.orders.update.put');
});
