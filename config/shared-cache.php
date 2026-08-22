<?php

declare(strict_types=1);

return [
    'prefix' => env('SHARED_CACHE_PREFIX', 'news'),

    'version' => env('SHARED_CACHE_VERSION', 'v1'),

    'timezone' => env('SHARED_CACHE_TIMEZONE', 'Asia/Dhaka'),

    // Cache store used to share data across applications. Must be a
    // tag-capable store (redis, memcached, array) for dependency-based
    // invalidation to work. Leave null to use the app's default store.
    'store' => env('SHARED_CACHE_STORE'),

    // Default TTL (seconds) for SharedCache::remember() when no TTL is given.
    'default_ttl' => (int) env('SHARED_CACHE_DEFAULT_TTL', 300),

    // [fresh_seconds, stale_seconds] for SharedCache::flexible(). Serves the
    // cached value immediately for the whole window; once past fresh_seconds
    // one request refreshes it in the background (Laravel's built-in
    // single-flight lock) while everyone else keeps getting the stale value
    // until stale_seconds. This is the primary cache-stampede defence for
    // high-traffic collection endpoints.
    'flexible_ttl' => [
        (int) env('SHARED_CACHE_FLEXIBLE_FRESH', 300),
        (int) env('SHARED_CACHE_FLEXIBLE_STALE', 900),
    ],

    // TTL (seconds) for SharedCache::rememberLong() - used in place of
    // rememberForever so a missed invalidation still self-heals eventually.
    'long_ttl' => (int) env('SHARED_CACHE_LONG_TTL', 86400),
];
