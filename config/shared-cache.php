<?php

declare(strict_types=1);

return [
    'prefix' => env('SHARED_CACHE_PREFIX', 'news'),

    'version' => env('SHARED_CACHE_VERSION', 'v1'),

    'timezone' => env('SHARED_CACHE_TIMEZONE', 'Asia/Dhaka'),

    // Cache store used to share data across applications. Leave null to use the app's default store.
    'store' => env('SHARED_CACHE_STORE'),
];
