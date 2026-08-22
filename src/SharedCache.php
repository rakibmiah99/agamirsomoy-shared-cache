<?php

declare(strict_types=1);

namespace Rakibmiah99\AgamirsomoySharedCache;

use BadMethodCallException;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central cache/invalidation service for the news platform.
 *
 * Design: every collection response is cached under a deterministic key
 * (CacheKey) and tagged with the entities it depends on (CacheTags). A
 * mutation invalidates by flushing the affected tags, not by deleting known
 * keys or scanning the keyspace - each Cache::tags([$tag])->flush() call
 * touches only the entries registered under that tag (a per-tag Redis set),
 * never the full keyspace.
 *
 * Tag flush alone does not stop a slow concurrent reader from writing stale
 * data back into the cache after a fresher rebuild already happened (the
 * physical cache key is deterministic, so a late write can simply recreate
 * it). remember()/rememberLong() close that gap with a small per-tag
 * generation counter: the generation of every tag is snapshotted before the
 * (possibly slow) rebuild callback runs and re-checked after; if any
 * snapshotted tag was invalidated in between, the freshly computed value is
 * still returned to that caller but is deliberately NOT written into the
 * shared cache, so it can never resurrect data newer code has already
 * superseded.
 */
class SharedCache
{
    private ?bool $storeSupportsTags = null;

    public function __construct(
        protected Repository $cache,
    ) {
    }

    public function store(): Repository
    {
        return $this->cache;
    }

    public function supportsTags(): bool
    {
        if ($this->storeSupportsTags !== null) {
            return $this->storeSupportsTags;
        }

        try {
            $this->cache->tags(['__shared_cache_probe__']);

            return $this->storeSupportsTags = true;
        } catch (BadMethodCallException) {
            return $this->storeSupportsTags = false;
        }
    }

    /**
     * @param  list<string>  $tags
     */
    protected function repositoryFor(array $tags): Repository
    {
        $tags = array_values(array_unique(array_filter($tags)));

        if ($tags === [] || ! $this->supportsTags()) {
            return $this->cache;
        }

        return $this->cache->tags($tags);
    }

    /**
     * Cache-aside read-through with dependency tags, stampede-locked and
     * stale-write protected on cache miss.
     *
     * @param  list<string>  $tags
     */
    public function remember(
        string $key,
        array $tags,
        Closure $callback,
        DateInterval|DateTimeInterface|int|null $ttl = null,
        int $lockWaitSeconds = 3,
    ): mixed {
        $ttl ??= (int) Config::get('shared-cache.default_ttl', 300);
        $tags = array_values(array_unique(array_filter($tags)));
        $repo = $this->repositoryFor($tags);

        $cached = $repo->get($key);
        if ($cached !== null) {
            return $cached;
        }

        if ($tags === []) {
            return $repo->remember($key, $ttl, $callback);
        }

        return $this->rebuildUnderLock($repo, $key, $tags, $callback, $ttl, $lockWaitSeconds);
    }

    /**
     * Stale-while-revalidate read-through: serves the fresh value while
     * within the first TTL bound, keeps serving the (stale) value while a
     * single request refreshes it in the background once the value ages
     * past the first bound but is still within the second. This is the
     * primary stampede defence for hot collection endpoints - Laravel's
     * flexible() already single-flights the refresh internally.
     *
     * @param  list<string>  $tags
     * @param  array{0:int,1:int}|null  $ttl  [fresh_seconds, stale_seconds]
     */
    public function flexible(string $key, array $tags, Closure $callback, ?array $ttl = null): mixed
    {
        $ttl ??= Config::get('shared-cache.flexible_ttl', [300, 900]);

        return $this->repositoryFor($tags)->flexible($key, $ttl, $callback);
    }

    /**
     * Long-lived cache with a finite safety-net TTL (never truly forever) so
     * a missed/failed invalidation self-heals eventually instead of serving
     * stale data permanently. Same stampede-lock and stale-write protection
     * as remember().
     *
     * @param  list<string>  $tags
     */
    public function rememberLong(string $key, array $tags, Closure $callback, ?int $ttl = null): mixed
    {
        $ttl ??= (int) Config::get('shared-cache.long_ttl', 86400);

        return $this->remember($key, $tags, $callback, $ttl);
    }

    /**
     * @param  list<string>  $tags
     */
    private function rebuildUnderLock(
        Repository $repo,
        string $key,
        array $tags,
        Closure $callback,
        DateInterval|DateTimeInterface|int $ttl,
        int $lockWaitSeconds,
    ): mixed {
        $build = function () use ($repo, $key, $tags, $callback, $ttl) {
            // Someone else may have rebuilt it while we were waiting for the lock.
            $cached = $repo->get($key);
            if ($cached !== null) {
                return $cached;
            }

            $before = $this->currentGenerations($tags);
            $value = $callback();
            $after = $this->currentGenerations($tags);

            if ($before !== $after) {
                // A dependency was invalidated while this value was being
                // computed - serve it to this caller but do not let it
                // become the shared cache entry.
                return $value;
            }

            $repo->put($key, $value, $ttl);

            return $value;
        };

        $lockTimeout = (int) Config::get('shared-cache.lock_timeout_seconds', 10);
        $lock = $this->cache->lock('shared-cache:build:' . $key, $lockTimeout);

        try {
            return $lock->block($lockWaitSeconds, $build);
        } catch (LockTimeoutException) {
            // Degrade gracefully under heavy contention: better to let this
            // one request compute without the lock than to fail it.
            return $build();
        }
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, int>
     */
    private function currentGenerations(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        $keys = array_map($this->generationKey(...), $tags);

        return array_map(
            static fn ($value) => (int) ($value ?? 0),
            $this->cache->many($keys)
        );
    }

    private function generationKey(string $tag): string
    {
        return 'shared-cache:gen:' . $tag;
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget($key);
    }

    /**
     * @param  list<string>  $keys
     */
    public function forgetMany(array $keys): void
    {
        foreach (array_unique($keys) as $key) {
            $this->cache->forget($key);
        }
    }

    /**
     * Flush the given dependency tags. O(number of tags), never a keyspace
     * scan or global flush - each Cache::tags([$tag])->flush() call is a
     * single Redis round trip that rotates that tag's namespace id.
     *
     * @param  list<string>  $tags
     */
    public function flushTags(array $tags): void
    {
        $tags = array_values(array_unique(array_filter($tags)));

        if ($tags === []) {
            return;
        }

        if (! $this->supportsTags()) {
            // Without a tag-capable store there is no correct, non-global way
            // to invalidate "everything that depends on entity X" - that gap
            // is exactly the root cause this package fixes. Log loudly rather
            // than pretending invalidation happened; the fix is to configure
            // a tag-capable cache store (redis/memcached/array), not to paper
            // over it here.
            Log::critical('shared-cache: cache store does not support tag-based invalidation, dependent caches were NOT invalidated. Set CACHE_STORE=redis.', [
                'tags' => $tags,
            ]);

            return;
        }

        foreach ($tags as $tag) {
            $this->cache->tags([$tag])->flush();
            $this->bumpGeneration($tag);
        }
    }

    private function bumpGeneration(string $tag): void
    {
        $key = $this->generationKey($tag);

        if ($this->cache->increment($key) === false) {
            $this->cache->put($key, 1);
        }
    }

    /**
     * Run the callback once the current DB transaction commits, or
     * immediately if there is no open transaction. Cache invalidation must
     * never happen before a commit - otherwise a concurrent reader can
     * repopulate the cache from data that is later rolled back or from a
     * pre-commit snapshot.
     */
    public function afterCommit(Closure $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }

    /**
     * Distributed lock for protecting a genuinely expensive rebuild that
     * isn't already covered by flexible()'s built-in single-flight refresh.
     */
    public function lock(string $name, int $seconds = 10, ?int $waitSeconds = null)
    {
        $lock = $this->cache->lock('shared-cache:lock:' . $name, $seconds);

        return $waitSeconds !== null ? $lock->block($waitSeconds) : $lock;
    }

    // ---------------------------------------------------------------
    // Domain-level invalidation helpers. Each is transaction-safe
    // (deferred via afterCommit) and always invalidates OLD and NEW
    // dependency values, never just the new one.
    // ---------------------------------------------------------------

    /**
     * Invalidate every cache dependent on a single news item, given its
     * current attribute values and (optionally) the values from before the
     * change. Pass $original when a mutation changed category/tags/authors
     * so both the old and new dependency tags are flushed - never only one.
     *
     * @param  array{id:int, slug?:string|null, category_id?:int|null, category_slug?:string|null, tag_ids?:int[], author_ids?:int[]}  $current
     * @param  array{slug?:string|null, category_id?:int|null, category_slug?:string|null, tag_ids?:int[], author_ids?:int[]}  $original
     */
    public function invalidateNews(array $current, array $original = []): void
    {
        $this->afterCommit(function () use ($current, $original) {
            $tags = [CacheTags::news((int) $current['id'])];

            $slugs = [$current['slug'] ?? null, $original['slug'] ?? null];
            $tags = array_merge($tags, CacheTags::many('news-slug', $slugs));

            $categoryIds = [$current['category_id'] ?? null, $original['category_id'] ?? null];
            $tags = array_merge($tags, CacheTags::many('category', $categoryIds));

            $categorySlugs = [$current['category_slug'] ?? null, $original['category_slug'] ?? null];
            $tags = array_merge($tags, CacheTags::many('category-slug', $categorySlugs));

            $tagIds = array_merge($current['tag_ids'] ?? [], $original['tag_ids'] ?? []);
            $tags = array_merge($tags, CacheTags::many('tag', $tagIds));

            $authorIds = array_merge($current['author_ids'] ?? [], $original['author_ids'] ?? []);
            $tags = array_merge($tags, CacheTags::many('author', $authorIds));

            if ($tagIds !== []) {
                $tags[] = CacheTags::header();
            }

            $this->flushTags($tags);
        });
    }

    /**
     * @param  int|array<int>  $categoryIds
     * @param  string|array<string|null>|null  $categorySlugs  pass old+new slug(s) on a rename; never only the new one
     */
    public function invalidateCategory(int|array $categoryIds, string|array|null $categorySlugs = null): void
    {
        $this->afterCommit(fn () => $this->flushTags([
            ...CacheTags::many('category', (array) $categoryIds),
            ...CacheTags::many('category-slug', array_filter((array) $categorySlugs)),
        ]));
    }

    public function invalidateCategoryTree(): void
    {
        $this->afterCommit(fn () => $this->flushTags([CacheTags::categoryTree()]));
    }

    /**
     * @param  int|array<int>  $tagIds
     */
    public function invalidateTag(int|array $tagIds): void
    {
        $this->afterCommit(fn () => $this->flushTags([
            ...CacheTags::many('tag', (array) $tagIds),
            CacheTags::header(),
        ]));
    }

    /**
     * @param  int|array<int>  $authorIds
     */
    public function invalidateAuthor(int|array $authorIds): void
    {
        $this->afterCommit(fn () => $this->flushTags(CacheTags::many('author', (array) $authorIds)));
    }

    /**
     * @param  string|array<string|null>|null  $sectionSlugs  old and/or new slug(s); nulls are ignored
     */
    public function invalidateSection(string|array|null $sectionSlugs): void
    {
        $slugs = array_filter((array) $sectionSlugs);

        if ($slugs === []) {
            return;
        }

        $this->afterCommit(fn () => $this->flushTags(CacheTags::many('section', $slugs)));
    }

    public function invalidateLatest(): void
    {
        $this->afterCommit(fn () => $this->flushTags([CacheTags::latestNews()]));
    }

    public function invalidateMarquee(): void
    {
        $this->afterCommit(fn () => $this->flushTags([CacheTags::marquee()]));
    }

    public function invalidateBreakingNews(): void
    {
        $this->afterCommit(fn () => $this->flushTags([CacheTags::breakingNews()]));
    }

    public function invalidateHeader(): void
    {
        $this->afterCommit(fn () => $this->flushTags([CacheTags::header()]));
    }

    public function invalidateMostRead(): void
    {
        $this->afterCommit(fn () => $this->flushTags([CacheTags::mostRead()]));
    }

    public function invalidateGeo(): void
    {
        $this->afterCommit(fn () => $this->flushTags([CacheTags::geo()]));
    }

    public function invalidateWebStory(): void
    {
        $this->afterCommit(fn () => $this->flushTags([CacheTags::webStory()]));
    }

    /**
     * Generic escape hatch for cases not covered by a named helper above.
     *
     * @param  list<string>  $tags
     */
    public function invalidateDependencies(array $tags): void
    {
        $this->afterCommit(fn () => $this->flushTags($tags));
    }
}
