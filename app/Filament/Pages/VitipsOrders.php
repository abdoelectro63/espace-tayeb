<?php

namespace App\Filament\Pages;

use App\Services\VitipsService;
use BackedEnum;
use Filament\Pages\Page;
use Throwable;
use UnitEnum;

class VitipsOrders extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'تتبع طلبيات شركة توصيل';

    protected static ?string $title = 'تتبع طلبيات شركة توصيل';

    protected static ?string $slug = 'vitips-orders';

    protected static string|UnitEnum|null $navigationGroup = 'الطلبيات';

    protected string $view = 'filament.pages.vitips-orders';

    public static function canAccess(): bool
    {
        return auth()->user()?->role !== 'delivery_man';
    }

    /**
     * @var list<array{
     *   tracking_number:string,
     *   customer_name:string,
     *   status:string,
     *   status_code:string,
     *   status_note:string,
     *   status_label:string,
     *   status_badge_bg:string,
     *   status_badge_text:string,
     *   city:string,
     *   total_amount:string
     * }>
     */
    public array $orders = [];

    public ?string $errorMessage = null;

    public int $currentPage = 1;

    public int $lastPage = 1;

    public int $total = 0;

    public function mount(VitipsService $vitipsService): void
    {
        $this->loadOrders($vitipsService);
    }

    public function refreshOrders(VitipsService $vitipsService): void
    {
        $this->currentPage = 1;
        $this->loadOrders($vitipsService);
    }

    public function goToPage(int $page, VitipsService $vitipsService): void
    {
        $this->currentPage = max(1, min($page, max(1, $this->lastPage)));
        $this->loadOrders($vitipsService);
    }

    private function loadOrders(VitipsService $vitipsService): void
    {
        $this->errorMessage = null;

        try {
            $result = $vitipsService->getOrdersPage($this->currentPage);
            $meta = $result['meta'];

            $this->currentPage = max(1, (int) ($meta['current_page'] ?? $this->currentPage));
            $this->lastPage = max(1, (int) ($meta['last_page'] ?? 1));
            $this->total = max(0, (int) ($meta['total'] ?? 0));

            $this->orders = collect($result['orders'])
                ->map(function (array $row): array {
                    $statusLabel = trim((string) ($row['status'] ?? 'غير معروف'));
                    $statusBg = $this->statusBadgeColor($statusLabel);

                    return [
                        ...$row,
                        'status_label' => $statusLabel === '' ? 'غير معروف' : $statusLabel,
                        'status_badge_bg' => $statusBg,
                        'status_badge_text' => $this->badgeTextColor($statusBg),
                    ];
                })
                ->all();
        } catch (Throwable $e) {
            report($e);
            $this->orders = [];
            $this->lastPage = 1;
            $this->total = 0;
            $this->errorMessage = 'تعذر جلب الطلبيات حاليا. يرجى المحاولة لاحقا.';
        }
    }

    private function statusBadgeColor(string $status): string
    {
        $normalized = mb_strtolower(trim($status), 'UTF-8');

        return match ($normalized) {
            'ramassé' => '#D6D6D6',
            'livré' => '#23D704',
            'réceptionné', 'pret pour expédition', 'prêt pour expédition', 'reçu par livreur',
            'retour réceptionné', 'colis retourné au stock', 'en cours de traitement' => '#12AAE7',
            'expédié' => '#0087FF',
            'retour client reçu' => '#278200',
            "demande de retour par l'admin", 'changement d\'adresse', 'demande de suivi', 'colis non reçu' => '#1C91FF',
            'change' => '#11EDF0',
            'échange retourné (livré)' => '#308500',
            'manqué (en ramassage)', 'injoignable' => '#FF8E00',
            'demande de retour', 'refusé', 'annuler', 'hors zone' => '#F54927',
            'reporté', 'repoté' => '#5C3000',
            'pas de réponse 1', 'pas de réponse 2', 'pas de reponse 3' => '#FF8100',
            'interessé' => '#05FF8B',
            'programmé', 'en voyage' => '#024ADE',
            'en cours de livraison' => '#00FFF7',
            default => '#6B7280',
        };
    }

    private function badgeTextColor(string $bgHex): string
    {
        $lightBackgrounds = ['#D6D6D6', '#11EDF0', '#00FFF7', '#05FF8B', '#12AAE7'];

        return in_array(strtoupper($bgHex), array_map('strtoupper', $lightBackgrounds), true)
            ? '#111827'
            : '#FFFFFF';
    }
}
