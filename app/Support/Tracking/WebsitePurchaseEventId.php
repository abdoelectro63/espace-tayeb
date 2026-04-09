<?php

namespace App\Support\Tracking;

use App\Models\Order;

/**
 * Single event_id for Meta Pixel + CAPI deduplication (website checkout only).
 */
final class WebsitePurchaseEventId
{
    public static function forOrder(Order $order): string
    {
        return 'purchase-web-'.$order->id;
    }
}
