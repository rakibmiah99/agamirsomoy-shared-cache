<?php

declare(strict_types=1);

namespace Rakibmiah99\AgamirsomoySharedCache;

use Illuminate\Support\ServiceProvider;

class SharedCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/shared-cache.php',
            'shared-cache'
        );

        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager(
                $app['cache']->store($app['config']->get('shared-cache.store'))
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/shared-cache.php'
            => $this->app->configPath('shared-cache.php'),
        ], 'shared-cache-config');
    }
}