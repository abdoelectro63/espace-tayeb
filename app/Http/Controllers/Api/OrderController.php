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
     * List orders as JSON. Delivery drivers: same scope as Filament {@see DeliveryResource} — only orders assigned to them
     * and only statuses shown on the delivery board (excludes e.g. pending). Staff see all orders.
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

        if ($user->role === 'delivery_man' && ! in_array($order->status, Order::DELIVERY_PANEL_STATUSES, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($order->status === 'delivered' && $this->isDeliveredAndPaid($order)) {
            return response()->json([
                'message' => 'Delivered and paid orders cannot be modified.',
            ], 403);
        }

        if ($order->status === 'delivered' && $user->role !== 'delivery_man') {
            return response()->json([
                'message' => 'Delivered orders cannot be modified.',
            ], 403);
        }

        $allowedStatuses = $this->allowedNewStatusesForUser($user);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in($allowedStatuses)],
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
            $query
                ->where('delivery_man_id', $user->id)
                ->whereIn('status', Order::DELIVERY_PANEL_STATUSES);
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

    /**
     * @return list<string>
     */
    private function allowedNewStatusesForUser(User $user): array
    {
        if ($user->role === 'delivery_man') {
            return Order::DELIVERY_MAN_ALLOWED_TRANSITION_STATUSES;
        }

        return Order::STATUSES;
    }

    private function isDeliveredAndPaid(Order $order): bool
    {
        return $order->status === 'delivered' && $order->payment_status === 'paid';
    }
}
