# Agamirsomoy Shared Cache

Shared Redis cache and dependency-based invalidation utilities for the Agamirsomoy news platform's Laravel applications.

Every collection response (home sections, category pages, header, most-read, etc.) is cached under a deterministic key and tagged with the entities it depends on (a news id, category, tag, author, section...). A mutation invalidates by **flushing the affected tags**, not by deleting known keys or scanning the keyspace — so invalidation cost is proportional to the number of changed dependencies, never the size of the cache.

## Requirements

- PHP ^8.2
- Laravel `illuminate/cache`, `illuminate/support`, `illuminate/redis` ^11.0 or ^12.0
- A tag-capable cache store (**Redis** recommended; Memcached and the `array` driver also support tags). Without one, dependency-based invalidation cannot work and the package logs a warning/critical error instead of silently doing a global flush.

## Installation

```bash
composer require rakibmiah99/agamirsomoy-shared-cache
```

The package auto-registers `SharedCacheServiceProvider` via Laravel package discovery.

Publish the config file if you want to customize it:

```bash
php artisan vendor:publish --tag=shared-cache-config
```

This creates `config/shared-cache.php`.

## Configuration

All options are driven by environment variables, with sane defaults:

| Env var | Default | Purpose |
|---|---|---|
| `SHARED_CACHE_PREFIX` | `news` | Prefix for all cache keys and tags (`{prefix}:{version}:...`). |
| `SHARED_CACHE_VERSION` | `v1` | Key namespace version — bump to invalidate everything at once. |
| `SHARED_CACHE_TIMEZONE` | `Asia/Dhaka` | Timezone used for date-based keys (e.g. `siteLatestNews()`). |
| `SHARED_CACHE_STORE` | *(app default)* | Cache store to use. Must be tag-capable (`redis`, `memcached`, `array`). Leave unset to use the app's default store. |
| `SHARED_CACHE_DEFAULT_TTL` | `300` | Default TTL (seconds) for `remember()`. |
| `SHARED_CACHE_FLEXIBLE_FRESH` | `300` | `flexible()` fresh window (seconds). |
| `SHARED_CACHE_FLEXIBLE_STALE` | `900` | `flexible()` stale window (seconds). |
| `SHARED_CACHE_LONG_TTL` | `86400` | TTL for `rememberLong()` — a finite safety net instead of forever, so a missed invalidation self-heals. |

## Core concepts

- **`SharedCache`** — the main service. Resolve it from the container or inject it.
- **`CacheKey`** — deterministic key builders, one per endpoint shape, plus a generic `make()`.
- **`CacheTags`** — dependency-tag name builders (news, category, tag, author, section, header, ...).
- **`CacheManager`** — *deprecated*, thin `forget()`-only wrapper kept for backward compatibility.

### Why tags + a generation counter

`Cache::tags([$tag])->flush()` only touches entries registered under that tag — never a keyspace scan or global flush. But a tag flush alone doesn't stop a slow concurrent reader from writing stale data back into the cache *after* a fresher rebuild already happened, since the physical key is deterministic and a late write can simply recreate it.

`remember()` / `rememberLong()` close that gap with a small per-tag generation counter: each dependency tag's generation is snapshotted before the (possibly slow) rebuild callback runs, and re-checked after. If any snapshotted tag was invalidated in between, the freshly computed value is still returned to that caller, but it is **not** written back into the shared cache — so it can never resurrect data that newer code has already superseded.

## Usage

### Reading through the cache

```php
use Rakibmiah99\AgamirsomoySharedCache\SharedCache;
use Rakibmiah99\AgamirsomoySharedCache\CacheKey;
use Rakibmiah99\AgamirsomoySharedCache\CacheTags;

class NewsController
{
    public function __construct(private SharedCache $cache) {}

    public function byCategory(string $slug)
    {
        return $this->cache->remember(
            key: CacheKey::newsByCategoryHome($slug),
            tags: [CacheTags::categoryBySlug($slug)],
            callback: fn () => News::query()->whereCategorySlug($slug)->paginate(),
        );
    }
}
```

- `remember(key, tags, callback, ttl = null, lockWaitSeconds = 3)` — cache-aside read-through, stampede-locked (via `Cache::lock()`) and stale-write protected on a cache miss. `ttl` defaults to `shared-cache.default_ttl`.
- `flexible(key, tags, callback, ttl = null)` — stale-while-revalidate: serves the cached value immediately for the whole window; once past the fresh bound, one request refreshes it in the background (Laravel's built-in single-flight lock) while everyone else keeps getting the stale value until the stale bound. Use this for hot, high-traffic collection endpoints. `ttl` is `[freshSeconds, staleSeconds]`, defaulting to `shared-cache.flexible_ttl`.
- `rememberLong(key, tags, callback, ttl = null)` — same protections as `remember()`, but with a long, finite TTL (`shared-cache.long_ttl`) instead of forever, so a missed/failed invalidation self-heals eventually.

If the resolved store doesn't support tags, `remember()`/`rememberLong()` fall back to plain (untagged) caching for that call.

### Invalidating on writes

Domain-level helpers cover the common mutation cases. They're all deferred until the current DB transaction commits (`afterCommit()`), and they flush **both old and new** dependency values when you pass `$original` — never only the new one, so a rename/re-categorize can't leave the old cache entry stranded.

```php
// Update a news item (pass $original when category/tags/authors changed)
$cache->invalidateNews($current, $original);

// Rename a category (old + new slug both flushed)
$cache->invalidateCategory($categoryId, [$oldSlug, $newSlug]);

$cache->invalidateCategoryTree();
$cache->invalidateTag($tagIds);
$cache->invalidateAuthor($authorIds);
$cache->invalidateSection([$oldSlug, $newSlug]);
$cache->invalidateLatest();
$cache->invalidateMarquee();
$cache->invalidateBreakingNews();
$cache->invalidateHeader();
$cache->invalidateMostRead();
$cache->invalidateGeo();
$cache->invalidateWebStory();

// Escape hatch for anything not covered above
$cache->invalidateDependencies([CacheTags::section('sports')]);
```

Lower-level primitives are also available:

```php
$cache->flushTags([CacheTags::category(5)]);   // flush specific tags directly
$cache->forget($key);                          // delete a single key
$cache->forgetMany([$key1, $key2]);
$cache->afterCommit(fn () => /* ... */);       // defer until commit, or run now if no transaction
$cache->lock('expensive-rebuild', seconds: 10, waitSeconds: 5); // named distributed lock
```

### Building keys and tags directly

`CacheKey` has one named builder per endpoint shape (`header()`, `newsByCategory()`, `siteLatestNews()`, `epaperReaderShow()`, ...) plus a generic normalizer:

```php
CacheKey::make('news-by-category', ['slug' => $slug, 'page' => $page]);
```

`make()` sorts params by name, drops null/empty values, and slugifies strings — so two calls with the same logical params, regardless of argument order, always resolve to the same key.

`CacheTags` builds dependency-tag names, and `CacheTags::many($type, $ids)` bulk-builds tags for a type (`news`, `category`, `category-slug`, `tag`, `author`, `section`, `news-slug`):

```php
CacheTags::many('category', [1, 2, 3]);
// => ['news:tag:category:1', 'news:tag:category:2', 'news:tag:category:3']
```

## Testing

The package ships Pest tests (unit tests for `CacheKey`/`SharedCache`, plus Redis integration tests):

```bash
composer install
vendor/bin/pest
```

Integration tests in `tests/Integration` require a reachable Redis instance (see `tests/RedisTestCase.php`).

## License

MIT
