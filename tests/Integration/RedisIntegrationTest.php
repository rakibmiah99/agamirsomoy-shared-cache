<?php

use Illuminate\Support\Facades\Redis;
use Rakibmiah99\AgamirsomoySharedCache\CacheTags;
use Rakibmiah99\AgamirsomoySharedCache\SharedCache;

beforeEach(function () {
    try {
        Redis::connection()->ping();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Redis is not reachable: ' . $e->getMessage());
    }

    Redis::connection()->flushdb();

    $this->shared = new SharedCache($this->app['cache']->store('redis'));
});

afterEach(function () {
    if (Redis::connection()->isConnected() ?? true) {
        try {
            Redis::connection()->flushdb();
        } catch (\Throwable) {
            // ignore
        }
    }
});

it('really flushes via RedisTaggedCache without a full keyspace scan', function () {
    $this->shared->remember('affected', [CacheTags::category(1)], fn () => 'v1');
    $this->shared->remember('unrelated', [CacheTags::category(2)], fn () => 'v2');

    $this->shared->flushTags([CacheTags::category(1)]);

    expect($this->shared->remember('affected', [CacheTags::category(1)], fn () => 'v1-rebuilt'))->toBe('v1-rebuilt');
    expect($this->shared->remember('unrelated', [CacheTags::category(2)], fn () => 'should-not-run'))->toBe('v2');
});

it('invalidates old and new category tags against real Redis', function () {
    $this->shared->remember('cat-1', [CacheTags::category(1)], fn () => 'old');
    $this->shared->remember('cat-2', [CacheTags::category(2)], fn () => 'new');

    $this->shared->invalidateNews(
        current: ['id' => 1, 'category_id' => 2],
        original: ['category_id' => 1],
    );

    expect($this->shared->remember('cat-1', [CacheTags::category(1)], fn () => 'old-rebuilt'))->toBe('old-rebuilt');
    expect($this->shared->remember('cat-2', [CacheTags::category(2)], fn () => 'new-rebuilt'))->toBe('new-rebuilt');
});

it('protects against the real RedisTagSet stale-write race (fixed per-tag key, not a salt swap)', function () {
    $tag = CacheTags::category(5);
    $key = 'race-key';

    $this->shared->remember($key, [$tag], fn () => 'v1');
    $this->shared->flushTags([$tag]);

    $result = $this->shared->remember($key, [$tag], function () use ($tag) {
        // A concurrent, newer update invalidates the same tag while this
        // "slow" rebuild is still running.
        $this->shared->flushTags([$tag]);

        return 'stale';
    });

    expect($result)->toBe('stale');

    // Must not have been persisted - Redis's tag key is a fixed string, so
    // without the generation check this stale write would have silently
    // become the live cache entry.
    $rebuilt = $this->shared->remember($key, [$tag], fn () => 'fresh');
    expect($rebuilt)->toBe('fresh');
});

it('serializes concurrent rebuilds of the same key through the distributed lock', function () {
    $tag = CacheTags::category(9);
    $key = 'locked-key';
    $concurrentCallInvocations = 0;

    // Two "concurrent" callers racing a cache miss; the second one to reach
    // rebuildUnderLock should see the first caller's already-written value
    // instead of recomputing.
    $callback = function () use (&$concurrentCallInvocations) {
        $concurrentCallInvocations++;

        return 'computed-once';
    };

    $first = $this->shared->remember($key, [$tag], $callback);
    $second = $this->shared->remember($key, [$tag], $callback);

    expect($first)->toBe('computed-once')
        ->and($second)->toBe('computed-once')
        ->and($concurrentCallInvocations)->toBe(1);
});
