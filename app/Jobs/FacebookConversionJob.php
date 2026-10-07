<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookConversionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public mixed $phone,
        public mixed $city,
        public mixed $address,
        public mixed $fbp,
        public mixed $fbc,
        public mixed $ip,
        public mixed $userAgent,
        public mixed $eventId,
        public mixed $total,
    ) {}

    public function handle(): void
    {
        $pixelId = trim((string) (config('tracking.facebook_pixel_id') ?: setting('facebook_pixel_id', '')));
        $token = trim((string) (config('tracking.facebook_capi_token') ?: setting('facebook_access_token', '')));

        if ($pixelId === '' || $token === '') {
            Log::warning('FacebookConversionJob: missing FACEBOOK_PIXEL_ID or FACEBOOK_CAPI_TOKEN');

            return;
        }

        $userData = array_filter([
            'ph' => $this->hashPhone($this->phone),
            'ct' => $this->hashLower($this->city),
            'st' => $this->hashLower($this->address),
            'country' => hash('sha256', 'ma'),
            'client_ip_address' => filled($this->ip) ? (string) $this->ip : null,
            'client_user_agent' => filled($this->userAgent) ? (string) $this->userAgent : null,
            'fbp' => filled($this->fbp) ? (string) $this->fbp : null,
            'fbc' => filled($this->fbc) ? (string) $this->fbc : null,
        ], fn ($v) => filled($v));

        $payload = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => (string) ($this->eventId ?: uniqid('evt_', true)),
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => [
                    'value' => round((float) $this->total, 2),
                    'currency' => 'MAD',
                ],
            ]],
        ];

        $url = sprintf(
            'https://graph.facebook.com/v19.0/%s/events?access_token=%s',
            $pixelId,
            urlencode($token)
        );

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('FacebookConversionJob: non-success response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('FacebookConversionJob: exception', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function hashPhone(mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Normalize Moroccan mobiles to country code digits (212…).
        if (str_starts_with($digits, '0')) {
            $digits = '212'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '212')) {
            $digits = '212'.$digits;
        }

        return hash('sha256', $digits);
    }

    private function hashLower(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return $normalized !== '' ? hash('sha256', $normalized) : null;
    }
}
