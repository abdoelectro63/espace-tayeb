<?php

return [

    'facebook_pixel_id' => env('FACEBOOK_PIXEL_ID'),

    'facebook_capi_token' => env('FACEBOOK_CAPI_TOKEN'),

    'facebook_graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v19.0'),

    'facebook_events_url' => env('FACEBOOK_EVENTS_URL'),

    /*
     * Default if FACEBOOK_EVENTS_URL is not set (pixel id substituted by service).
     */
    'facebook_events_url_template' => 'https://graph.facebook.com/%s/%s/events',

    /*
     * TikTok Events API (server-side).
     * @see https://ads.tiktok.com/marketing_api/docs
     */
    'tiktok_events_url' => env('TIKTOK_EVENTS_URL', 'https://business-api.tiktok.com/open_api/v1.3/pixel/track/'),

];
