<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * List orders as JSON. Scoped like Filament: staff roles see all (or filtered); delivery drivers only their assignments.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->userMayUseOrdersApi($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = $this->baseOrderQuery($user);

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders)
            ->response()
            ->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    /**
     * Update order status only (English slugs: pending, confirmed, shipped, delivered, …).
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if (! $this->userMayUseOrdersApi($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $this->userMayAccessOrder($user, $order)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($order->status === 'delivered') {
            return response()->json([
                'message' => 'Delivered orders cannot be modified.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Order::STATUSES)],
        ]);

        $order->update(['status' => $validated['status']]);

        $order->load(['orderItems.product', 'deliveryMan']);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(200)
            ->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    private function userMayUseOrdersApi(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return in_array($user->role, ['admin', 'delivery_man', 'confirmation', 'manager'], true);
    }

    /**
     * @return Builder<Order>
     */
    private function baseOrderQuery(User $user): Builder
    {
        $query = Order::query()
            ->with(['orderItems.product', 'deliveryMan'])
            ->orderByDesc('created_at');

        if ($user->role === 'delivery_man') {
            $query->where('delivery_man_id', $user->id);
        }

        return $query;
    }

    private function userMayAccessOrder(User $user, Order $order): bool
    {
        if (in_array($user->role, ['admin', 'confirmation', 'manager'], true)) {
            return true;
        }

        if ($user->role === 'delivery_man') {
            return (int) $order->delivery_man_id === (int) $user->id;
        }

        return false;
    }
}
