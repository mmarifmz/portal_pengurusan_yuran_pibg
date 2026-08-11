<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Would you like the install button to appear on all pages?
      Set true/false
    |--------------------------------------------------------------------------
    */

    'install-button' => true,

    /*
    |--------------------------------------------------------------------------
    | PWA Manifest Configuration
    |--------------------------------------------------------------------------
    |  php artisan erag:update-manifest
    */

    'manifest' => [
        'name' => env('APP_NAME', 'Jogathon Digital SK Sri Petaling'),
        'short_name' => env('PWA_SHORT_NAME', 'Jogathon'),
        'background_color' => env('PWA_BACKGROUND_COLOR', '#020617'),
        'display' => env('PWA_DISPLAY', 'standalone'),
        'description' => env('PWA_DESCRIPTION', 'Mini app Jogathon Digital SK Sri Petaling untuk peserta, kad kutipan digital dan analitik kempen.'),
        'theme_color' => env('PWA_THEME_COLOR', '#0f172a'),
        'icons' => [
            [
                'src' => 'logo.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    | Toggles the application's debug mode based on the environment variable
    */

    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Livewire Integration
    |--------------------------------------------------------------------------
    | Set to true if you're using Livewire in your application to enable
    | Livewire-specific PWA optimizations or features.
    */

    'livewire-app' => false,
];
