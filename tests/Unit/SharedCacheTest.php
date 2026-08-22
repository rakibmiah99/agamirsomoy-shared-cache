<?php

use Illuminate\Support\Facades\DB;
use Rakibmiah99\AgamirsomoySharedCache\CacheTags;
use Rakibmiah99\AgamirsomoySharedCache\SharedCache;

beforeEach(function () {
    $this->shared = app(SharedCache::class);
});

it('caches through remember() and only invokes the callback once', function () {
    $calls = 0;
    $callback = function () use (&$calls) {
        $calls++;

        return 'value';
    };

    $first = $this->shared->remember('k1', ['t1'], $callback);
    $second = $this->shared->remember('k1', ['t1'], $callback);

    expect($first)->toBe('value')
        ->and($second)->toBe('value')
        ->and($calls)->toBe(1);
});

it('flushing a tag invalidates only entries carrying that tag', function () {
    $affected = $this->shared->remember('affected-key', [CacheTags::category(1)], fn () => 'v1');
    $unrelated = $this->shared->remember('unrelated-key', [CacheTags::category(2)], fn () => 'v2');

    expect($affected)->toBe('v1')->and($unrelated)->toBe('v2');

    $this->shared->flushTags([CacheTags::category(1)]);

    $rebuiltAffected = $this->shared->remember('affected-key', [CacheTags::category(1)], fn () => 'v1-rebuilt');
    $stillCachedUnrelated = $this->shared->remember('unrelated-key', [CacheTags::category(2)], fn () => 'should-not-run');

    expect($rebuiltAffected)->toBe('v1-rebuilt')
        ->and($stillCachedUnrelated)->toBe('v2');
});

it('invalidateNews flushes BOTH the old and the new category tag on a category change', function () {
    $oldCategoryPage = $this->shared->remember('cat-1-page', [CacheTags::category(1)], fn () => 'old-cat-page');
    $newCategoryPage = $this->shared->remember('cat-2-page', [CacheTags::category(2)], fn () => 'new-cat-page');
    $unrelatedCategoryPage = $this->shared->remember('cat-3-page', [CacheTags::category(3)], fn () => 'cat-3-page');

    expect($oldCategoryPage)->toBe('old-cat-page')
        ->and($newCategoryPage)->toBe('new-cat-page')
        ->and($unrelatedCategoryPage)->toBe('cat-3-page');

    $this->shared->invalidateNews(
        current: ['id' => 10, 'category_id' => 2],
        original: ['category_id' => 1],
    );

    $rebuiltOld = $this->shared->remember('cat-1-page', [CacheTags::category(1)], fn () => 'old-cat-page-rebuilt');
    $rebuiltNew = $this->shared->remember('cat-2-page', [CacheTags::category(2)], fn () => 'new-cat-page-rebuilt');
    $stillCachedUnrelated = $this->shared->remember('cat-3-page', [CacheTags::category(3)], fn () => 'should-not-run');

    expect($rebuiltOld)->toBe('old-cat-page-rebuilt')
        ->and($rebuiltNew)->toBe('new-cat-page-rebuilt')
        ->and($stillCachedUnrelated)->toBe('cat-3-page');
});

it('invalidateNews flushes removed AND added tag/author ids, never only the new ones', function () {
    $tagElectionPage = $this->shared->remember('tag-election', [CacheTags::tag(1)], fn () => 'election-page');
    $tagFootballPage = $this->shared->remember('tag-football', [CacheTags::tag(2)], fn () => 'football-page');
    $authorOldPage = $this->shared->remember('author-10', [CacheTags::author(10)], fn () => 'author-10-page');
    $authorNewPage = $this->shared->remember('author-20', [CacheTags::author(20)], fn () => 'author-20-page');

    $this->shared->invalidateNews(
        current: ['id' => 123, 'category_id' => null, 'tag_ids' => [2], 'author_ids' => [20]],
        original: ['category_id' => null, 'tag_ids' => [1], 'author_ids' => [10]],
    );

    expect($this->shared->remember('tag-election', [CacheTags::tag(1)], fn () => 'rebuilt'))->toBe('rebuilt')
        ->and($this->shared->remember('tag-football', [CacheTags::tag(2)], fn () => 'rebuilt'))->toBe('rebuilt')
        ->and($this->shared->remember('author-10', [CacheTags::author(10)], fn () => 'rebuilt'))->toBe('rebuilt')
        ->and($this->shared->remember('author-20', [CacheTags::author(20)], fn () => 'rebuilt'))->toBe('rebuilt');
});

it('invalidateNews flushes both the old and the new slug tag on a slug rename', function () {
    $oldSlugPage = $this->shared->remember('detail-old-slug', [CacheTags::newsBySlug('old-slug')], fn () => 'old-detail');
    $newSlugPage = $this->shared->remember('detail-new-slug', [CacheTags::newsBySlug('new-slug')], fn () => 'new-detail');

    $this->shared->invalidateNews(
        current: ['id' => 55, 'slug' => 'new-slug'],
        original: ['slug' => 'old-slug'],
    );

    expect($this->shared->remember('detail-old-slug', [CacheTags::newsBySlug('old-slug')], fn () => 'rebuilt'))->toBe('rebuilt')
        ->and($this->shared->remember('detail-new-slug', [CacheTags::newsBySlug('new-slug')], fn () => 'rebuilt'))->toBe('rebuilt');
});

it('defers invalidation until the DB transaction commits, never before', function () {
    $key = 'tx-key';
    $tag = CacheTags::category(99);

    $this->shared->remember($key, [$tag], fn () => 'before-update');

    DB::beginTransaction();
    $this->shared->invalidateCategory(99);

    // Not committed yet - the cache must still serve the pre-update value.
    $stillOld = $this->shared->remember($key, [$tag], fn () => 'should-not-run-yet');
    expect($stillOld)->toBe('before-update');

    DB::commit();

    // Now that the transaction committed, invalidation must have run.
    $rebuilt = $this->shared->remember($key, [$tag], fn () => 'after-commit');
    expect($rebuilt)->toBe('after-commit');
});

it('does not invalidate at all when the transaction rolls back', function () {
    $key = 'tx-rollback-key';
    $tag = CacheTags::category(77);

    $this->shared->remember($key, [$tag], fn () => 'original');

    DB::beginTransaction();
    $this->shared->invalidateCategory(77);
    DB::rollBack();

    $stillOriginal = $this->shared->remember($key, [$tag], fn () => 'should-not-run');
    expect($stillOriginal)->toBe('original');
});

it('protects remember() from persisting a rebuild that finishes after a newer invalidation', function () {
    $tag = CacheTags::category(5);
    $key = 'race-key';

    $this->shared->remember($key, [$tag], fn () => 'v1');

    // Force a miss, as if an update just invalidated this dependency.
    $this->shared->flushTags([$tag]);

    // "Request A" starts rebuilding from a (slow) pre-update read. While its
    // callback is still running, "Request B" lands a newer update and
    // invalidates the same dependency again.
    $result = $this->shared->remember($key, [$tag], function () use ($tag) {
        $this->shared->flushTags([$tag]);

        return 'stale-value-based-on-old-read';
    });

    // Request A still gets its own (now-stale) computed value back...
    expect($result)->toBe('stale-value-based-on-old-read');

    // ...but it must NOT have been persisted as the shared cache entry,
    // since a newer invalidation happened while it was computing. The next
    // read is therefore a genuine miss that rebuilds from fresh data.
    $rebuilt = $this->shared->remember($key, [$tag], fn () => 'fresh-value');
    expect($rebuilt)->toBe('fresh-value');
});

it('flexible() serves the value without recomputation within the fresh window', function () {
    $calls = 0;
    $callback = function () use (&$calls) {
        $calls++;

        return 'flexible-value';
    };

    $first = $this->shared->flexible('flex-key', ['flex-tag'], $callback, [60, 120]);
    $second = $this->shared->flexible('flex-key', ['flex-tag'], $callback, [60, 120]);

    expect($first)->toBe('flexible-value')
        ->and($second)->toBe('flexible-value')
        ->and($calls)->toBe(1);
});

it('CacheTags::many dedupes and drops null/empty ids', function () {
    $tags = CacheTags::many('category', [1, 1, null, '', 2]);

    expect($tags)->toBe([CacheTags::category(1), CacheTags::category(2)]);
});
