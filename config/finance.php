<?php

return [
    // Default API URL (bisa di-override dari database settings)
    'api_url' => env('FINANCE_API_URL', ''),

    // Default API Token (bisa di-override dari database settings)
    'api_token' => env('FINANCE_API_TOKEN', ''),

    // Auto sync
    'auto_sync_enabled' => env('FINANCE_AUTO_SYNC', false),
    'auto_sync_time' => env('FINANCE_SYNC_TIME', '00:00'),
    'sync_timeout' => env('FINANCE_SYNC_TIMEOUT', 30),
];
