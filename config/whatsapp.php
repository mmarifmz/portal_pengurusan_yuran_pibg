<?php

return [
    'app_name' => env('WHATSAPP_APP_NAME', env('APP_NAME', 'Portal Yuran PIBG')),
    'send_interval_seconds' => (int) env('WHATSAPP_SEND_INTERVAL_SECONDS', 20),
    'class_gap_seconds' => (int) env('WHATSAPP_CLASS_GAP_SECONDS', 30),
    'max_pending_before_warning' => (int) env('WHATSAPP_MAX_PENDING_BEFORE_WARNING', 10),
    'account_protection_mode' => (bool) env('WHATSAPP_ACCOUNT_PROTECTION_MODE', true),
    'shared_session' => (bool) env('WASENDER_SHARED_SESSION', true),
    'api_send_lock_seconds' => (int) env('WHATSAPP_API_SEND_LOCK_SECONDS', 60),
];
