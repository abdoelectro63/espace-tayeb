<?php

namespace App\Services\Tracking;

use App\Models\Order;
use App\Settings\TrackingSettings;
use App\Support\Tracking\TrackingHasher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FacebookConversionService
{
    public function __construct(
        private TrackingSettings $settings
    ) {}

    /**
     * Server-side Purchase for website checkout (thank-you page flow only).
     *
     * @see https://developers.facebook.com/docs/meta-pixel/implementation/conversion-tracking/
     */
    public function sendPurchase(Order $order, string $eventId, ?string $clientIp, ?string $userAgent): bool
    {
        $pixelId = trim($this->settings->facebook_pixel_id ?? '');
        $token = trim($this->settings->facebook_access_token ?? '');

        if ($pixelId === '' || $token === '') {
            return false;
        }

        $order->loadMissing('orderItems.product');

        $contentIds = $order->orderItems
            ->map(fn ($line) => (string) ($line->product_id ?? ''))
            ->filter()
            ->values()
            ->all();

        $value = round((float) $order->total_price, 2);
        $eventTime = time();

        $userData = array_filter([
            'client_ip_address' => $clientIp,
            'client_user_agent' => $userAgent,
        ], fn ($v) => filled($v));

        $phHash = TrackingHasher::hashPhone($order->customer_phone);
        if ($phHash !== null) {
            $userData['ph'] = [$phHash];
        }

        $eventRow = [
            'event_name' => 'Purchase',
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'action_source' => 'website',
            'user_data' => $userData,
            'custom_data' => array_filter([
                'value' => $value,
                'currency' => 'MAD',
                'content_ids' => $contentIds,
                'content_type' => 'product',
            ]),
        ];

        $form = [
            'access_token' => $token,
            'data' => json_encode([$eventRow], JSON_UNESCAPED_SLASHES),
        ];

        $testCode = trim($this->settings->facebook_test_event_code ?? '');
        if ($testCode !== '') {
            $form['test_event_code'] = $testCode;
        }

        $version = config('tracking.facebook_graph_version', 'v18.0');
        $url = config('tracking.facebook_events_url');
        if (! is_string($url) || $url === '') {
            $url = sprintf(
                (string) config('tracking.facebook_events_url_template'),
                $version,
                $pixelId
            );
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post($url, $form);

            if (! $response->successful()) {
                Log::warning('Meta Conversion API: non-success response', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $json = $response->json() ?? [];
            if (array_key_exists('events_received', $json) && (int) $json['events_received'] < 1) {
                Log::warning('Meta Conversion API: events_received = 0', [
                    'order_id' => $order->id,
                    'body' => $json,
                ]);

                return false;
            }

            if ($this->settings->tracking_debug) {
                Log::debug('Meta Conversion API: Purchase sent', [
                    'order_id' => $order->id,
                    'event_id' => $eventId,
                    'response' => $json,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Meta Conversion API: exception', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
