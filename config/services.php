<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'vitips' => [
        'token' => env('VITIPS_TOKEN'),
        'base_url' => env('VITIPS_BASE_URL', 'https://app.vitipsexpress.com/api/v1'),
        // add-colis: send exact dropdown label (CASABLANCA) or numeric option id — try "label" if API rejects id
        'city_value' => env('VITIPS_CITY_VALUE', 'label'), // label|id
    ],

    /*
    | Optional: Poppler pdftotext (much better table text than PHP-only parsers on many PDFs).
    | Install: https://github.com/oschwartz10612/poppler-windows (add bin to PATH) or apt install poppler-utils
    | Leave null to auto-detect "pdftotext" on PATH.
    */
    'pdf' => [
        'pdftotext_binary' => env('PDFTOTEXT_BINARY'),
        // smalot/pdfparser peut consommer beaucoup de RAM sur certains PDF — désactivé au-delà de ce poids.
        'smalot_max_bytes' => (int) env('PDF_SMALOT_MAX_BYTES', 2 * 1024 * 1024),
        'smalot_memory_limit' => env('PDF_SMALOT_MEMORY_LIMIT', '512M'),
    ],

];
