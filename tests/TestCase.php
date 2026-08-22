<?php

declare(strict_types=1);

namespace Rakibmiah99\AgamirsomoySharedCache\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Rakibmiah99\AgamirsomoySharedCache\SharedCacheServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SharedCacheServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('shared-cache.prefix', 'test');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
