<?php

namespace App\Http\Controllers;

use App\Services\VitipsService;
use Illuminate\Http\JsonResponse;
use Throwable;

class VitipsController extends Controller
{
    public function orders(VitipsService $vitipsService): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'orders' => $vitipsService->getOrders(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'تعذر جلب الطلبيات من شركة التوصيل حاليا.',
            ], 503);
        }
    }
}
