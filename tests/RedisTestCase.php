<?php

declare(strict_types=1);

namespace Rakibmiah99\AgamirsomoySharedCache\Tests;

use Illuminate\Redis\RedisServiceProvider;
use Rakibmiah99\AgamirsomoySharedCache\SharedCacheServiceProvider;

abstract class RedisTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SharedCacheServiceProvider::class,
            RedisServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.redis.client', env('REDIS_CLIENT', 'phpredis'));
        $app['config']->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD') ?: null,
            'port' => (int) env('REDIS_PORT', 6379),
            // Dedicated DB index so package tests never collide with a real app's data.
            'database' => (int) env('REDIS_TEST_DATABASE', 15),
        ]);
        $app['config']->set('cache.stores.redis', [
            'driver' => 'redis',
            'connection' => 'default',
            'lock_connection' => 'default',
        ]);
    }
}
