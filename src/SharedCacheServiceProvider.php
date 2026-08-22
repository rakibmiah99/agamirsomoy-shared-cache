<?php

declare(strict_types=1);

namespace Rakibmiah99\AgamirsomoySharedCache;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class SharedCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/shared-cache.php',
            'shared-cache'
        );

        $this->app->singleton(SharedCache::class, function ($app) {
            return new SharedCache(
                $app['cache']->store($app['config']->get('shared-cache.store'))
            );
        });

        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager($app->make(SharedCache::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/shared-cache.php'
            => $this->app->configPath('shared-cache.php'),
        ], 'shared-cache-config');

        if (! $this->app->runningUnitTests()) {
            $this->warnIfStoreCannotTag();
        }
    }

    private function warnIfStoreCannotTag(): void
    {
        /** @var SharedCache $shared */
        $shared = $this->app->make(SharedCache::class);

        if (! $shared->supportsTags()) {
            Log::warning('shared-cache: the configured cache store does not support tags. Dependency-based cache invalidation will NOT work correctly. Set CACHE_STORE=redis (or SHARED_CACHE_STORE) to a tag-capable store.');
        }
    }
}
