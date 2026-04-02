<?php

namespace App\Filament\Pages;

use App\Models\Order;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class DeliveryManOrdersPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $title = 'My Orders';

    protected static ?string $slug = 'delivery-orders';

    protected static string|UnitEnum|null $navigationGroup = 'توصيلs';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.delivery-man-orders-page';

    /** @var list<Order> */
    public array $orders = [];

    public int $currentPage = 1;

    public int $lastPage = 1;

    public int $total = 0;

    public int $perPage = 10;

    public string $activeTab = 'delivered_unpaid';

    public int $deliveredUnpaidCount = 0;

    public int $paidCompletedCount = 0;

    /** @var array<int, string> */
    public array $statusInputs = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'delivery_man';
    }

    public function mount(): void
    {
        $this->loadOrders();
    }

    public function goToPage(int $page): void
    {
        $this->currentPage = max(1, min($page, max(1, $this->lastPage)));
        $this->loadOrders();
    }

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['delivered_unpaid', 'paid_completed'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->currentPage = 1;
        $this->loadOrders();
    }

    public function updateOrderStatus(int $orderId): void
    {
        if ($this->activeTab === 'paid_completed') {
            return;
        }

        $newStatus = $this->statusInputs[$orderId] ?? null;
        $allowed = ['delivered', 'cancelled', 'no_response', 'refuse', 'reporter'];

        if (! is_string($newStatus) || ! in_array($newStatus, $allowed, true)) {
            return;
        }

        $order = Order::query()
            ->whereKey($orderId)
            ->where('delivery_man_id', auth()->id())
            ->where(function ($query): void {
                $query->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid');
            })
            ->first();

        if (! $order) {
            return;
        }

        $order->update([
            'status' => $newStatus,
        ]);

        $this->loadOrders();
    }

    private function loadOrders(): void
    {
        $base = Order::query()
            ->where('delivery_man_id', auth()->id())
            ->whereIn('status', ['confirmed', 'shipped', 'delivered', 'no_response', 'cancelled', 'refuse', 'reporter']);

        $this->deliveredUnpaidCount = (clone $base)
            ->where(function ($query): void {
                $query->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid');
            })
            ->count();

        $this->paidCompletedCount = (clone $base)
            ->where('status', 'delivered')
            ->where('payment_status', 'paid')
            ->count();

        $base = match ($this->activeTab) {
            'paid_completed' => $base
                ->where('status', 'delivered')
                ->where('payment_status', 'paid'),
            default => $base->where(function ($query): void {
                $query->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid');
            }),
        };

        $base->orderByDesc('created_at');

        $this->total = (clone $base)->count();
        $this->lastPage = max(1, (int) ceil($this->total / $this->perPage));
        $this->currentPage = min($this->currentPage, $this->lastPage);

        $offset = ($this->currentPage - 1) * $this->perPage;

        $records = (clone $base)
            ->offset($offset)
            ->limit($this->perPage)
            ->get();

        $this->orders = $records
            ->map(function (Order $order): array {
                return [
                    'id' => $order->id,
                    'number' => $order->number,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'city' => $order->city,
                    'shipping_address' => $order->shipping_address,
                    'status' => $order->status,
                    'status_label' => $this->statusLabel($order->status),
                    'status_color' => $this->statusColor($order->status),
                    'payment_status' => $order->payment_status ?: 'unpaid',
                    'total_price' => (float) $order->total_price,
                    'created_at' => $order->created_at?->format('Y-m-d H:i') ?: '—',
                ];
            })
            ->all();

        $this->statusInputs = $records
            ->mapWithKeys(fn (Order $order): array => [$order->id => (string) $order->status])
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'في الانتظار',
            'confirmed' => 'مؤكّد',
            'no_response' => 'لا يجيب',
            'cancelled' => 'ملغى',
            'refuse' => 'مرفوض',
            'reporter' => 'مؤجّل',
            'shipped' => 'مُرسل',
            'delivered' => 'تم توصيل',
            default => $status,
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'confirmed' => 'success',
            'shipped' => 'warning',
            'delivered' => 'success',
            'cancelled', 'refuse' => 'danger',
            'no_response' => 'warning',
            'reporter' => 'info',
            default => 'gray',
        };
    }
}
