<?php

namespace App\Services\Tracking;

use App\Models\Order;
use App\Settings\TrackingSettings;
use App\Support\Tracking\TrackingHasher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TikTokEventService
{
    public function __construct(
        private TrackingSettings $settings
    ) {}

    /**
     * Server-side CompletePayment (website checkout thank-you page only).
     *
     * @see https://ads.tiktok.com/help/article/standard-events-parameters
     */
    public function sendCompletePayment(Order $order, string $eventId, ?string $clientIp, ?string $userAgent): bool
    {
        $pixelCode = trim($this->settings->tiktok_pixel_id ?? '');
        $accessToken = trim($this->settings->tiktok_access_token ?? '');

        if ($pixelCode === '' || $accessToken === '') {
            return false;
        }

        $order->loadMissing('orderItems.product');

        $contents = [];
        foreach ($order->orderItems as $line) {
            $unit = round((float) $line->unit_price, 2);
            $qty = max(1, (int) $line->quantity);
            $contents[] = [
                'content_id' => (string) ($line->product_id ?? $line->id),
                'quantity' => $qty,
                'price' => $unit,
            ];
        }

        $payload = [
            'pixel_code' => $pixelCode,
            'event' => 'CompletePayment',
            'event_id' => $eventId,
            'timestamp' => now()->toIso8601String(),
            'properties' => [
                'currency' => 'MAD',
                'value' => round((float) $order->total_price, 2),
                'contents' => $contents,
            ],
            'context' => array_filter([
                'user' => array_filter([
                    'phone' => TrackingHasher::hashPhone($order->customer_phone),
                ]),
                'ip' => $clientIp,
                'user_agent' => $userAgent,
            ]),
        ];

        $url = (string) config('tracking.tiktok_events_url');

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('TikTok Events API: non-success response', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $json = $response->json();
            $code = $json['code'] ?? null;
            if ($code !== null && (int) $code !== 0) {
                Log::warning('TikTok Events API: business error code', [
                    'order_id' => $order->id,
                    'response' => $json,
                ]);

                return false;
            }

            if ($this->settings->tracking_debug) {
                Log::debug('TikTok Events API: CompletePayment sent', [
                    'order_id' => $order->id,
                    'event_id' => $eventId,
                    'response' => $json,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('TikTok Events API: exception', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
