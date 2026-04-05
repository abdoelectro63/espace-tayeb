<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DeliveryBenefitOrderResource;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Livreur : gains liés aux frais de livraison (delivery_fee) sur commandes livrées / clôturées.
 * Période = mois calendaire (aligné sur le widget Filament DeliveryMonthlyFeesChart, filtre updated_at).
 */
class DeliveryBenefitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || $user->role !== 'delivery_man') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $monthInput = $request->query('month');
        if ($monthInput !== null && $monthInput !== '' && ! preg_match('/^\d{4}-\d{2}$/', (string) $monthInput)) {
            return response()->json([
                'message' => 'Invalid month. Use YYYY-MM.',
            ], 422);
        }

        $tz = config('app.timezone', 'UTC');
        if ($monthInput !== null && $monthInput !== '') {
            $start = Carbon::createFromFormat('Y-m', (string) $monthInput, $tz)->startOfMonth();
        } else {
            $start = now($tz)->startOfMonth();
        }
        $end = (clone $start)->endOfMonth();

        /** @var Builder<Order> $base */
        $base = Order::query()
            ->where('delivery_man_id', $user->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->where('delivery_fee', '>', 0)
            ->whereBetween('updated_at', [$start, $end]);

        $totalDeliveryFees = (float) (clone $base)->sum('delivery_fee');
        $ordersWithFeeCount = (clone $base)->count();

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $paginated = (clone $base)
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        $rows = array_map(
            static fn (Order $order): array => (new DeliveryBenefitOrderResource($order))->toArray($request),
            $paginated->items()
        );

        return response()->json([
            'period' => [
                'month' => $start->format('Y-m'),
                'from' => $start->toIso8601String(),
                'to' => $end->toIso8601String(),
            ],
            'total_delivery_fees' => $totalDeliveryFees,
            'orders_with_fee_count' => $ordersWithFeeCount,
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
