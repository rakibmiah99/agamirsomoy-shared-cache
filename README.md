# Agamirsomoy Shared Cache

Shared cache utilities for Laravel applications. Provides a single, versioned source of truth for cache key naming and a `CacheManager` for invalidating those keys, so multiple applications (or multiple parts of the same application) sharing a cache store stay in sync.

## Requirements

- PHP ^8.2
- `illuminate/cache` ^11.0 | ^12.0
- `illuminate/support` ^11.0 | ^12.0

## Installation

```bash
composer require rakibmiah99/agamirsomoy-shared-cache
```

### Register the service provider

Laravel package auto-discovery is configured to load `AgamirsomoySharedCacheServiceProvider`, but the class shipped in this package is `SharedCacheServiceProvider`. Until that's fixed upstream, register it manually in `bootstrap/providers.php` (Laravel 11+) or `config/app.php`:

```php
Rakibmiah99\AgamirsomoySharedCache\SharedCacheServiceProvider::class,
```

### Publish the config (optional)

```bash
php artisan vendor:publish --tag=shared-cache-config
```

This publishes `config/shared-cache.php`.

## Configuration

| Env variable | Config key | Default | Description |
|---|---|---|---|
| `SHARED_CACHE_PREFIX` | `shared-cache.prefix` | `news` | Prefix used at the start of every generated cache key. |
| `SHARED_CACHE_VERSION` | `shared-cache.version` | `v1` | Version segment included in every key; bump it to invalidate all keys at once. |
| `SHARED_CACHE_TIMEZONE` | `shared-cache.timezone` | `Asia/Dhaka` | Timezone used to compute "today" for date-based keys. |
| `SHARED_CACHE_STORE` | `shared-cache.store` | `null` (app default store) | Cache store used by `CacheManager`. Set this so all applications sharing the cache point at the same store (e.g. Redis). |

Generated keys look like: `{prefix}:{version}:{segment}[:...params]`, for example `news:v1:news-details:some-slug`.

## Usage

### Generating cache keys — `CacheKey`

`CacheKey` is a static helper that builds consistent, prefixed cache keys. Use it wherever you read or write shared cache entries so the naming scheme stays identical across applications.

```php
use Rakibmiah99\AgamirsomoySharedCache\CacheKey;
use Illuminate\Support\Facades\Cache;

$news = Cache::remember(
    CacheKey::newsDetails($slug),
    now()->addHour(),
    fn () => News::bySlug($slug)->firstOrFail()
);
```

Available key builders:

| Method | Example output |
|---|---|
| `CacheKey::header()` | `news:v1:header:all` |
| `CacheKey::headerTag(string $tagSlug)` | `news:v1:header:tag:{slug}` |
| `CacheKey::category()` | `news:v1:category:all` |
| `CacheKey::marque()` | `news:v1:marquee:all` |
| `CacheKey::breakingNews()` | `news:v1:breaking:all` |
| `CacheKey::divisions()` | `news:v1:geo-location:divisions` |
| `CacheKey::districts($divisionSlug)` | `news:v1:geo-location:districts:{divisionSlug}` |
| `CacheKey::upazilas($districtSlug)` | `news:v1:geo-location:upazilas:{districtSlug}` |
| `CacheKey::homeSectionWiseNews($sectionName)` | `news:v1:home-section-wise-news:{sectionName}` |
| `CacheKey::siteLatestNews(?string $date = null)` | `news:v1:site-latest-news:{Y-m-d}` (defaults to today) |
| `CacheKey::siteMostReadNews(?string $readDate = null)` | `news:v1:site-most-read-news:{Y-m-d}` (defaults to today) |
| `CacheKey::mostReadNewsByCategory(int $categoryId, int $limit = 15)` | `news:v1:most-read-by-category:{categoryId}:{limit}` |
| `CacheKey::newsDetails($slugKey)` | `news:v1:news-details:{slug}` |
| `CacheKey::newsByCategoryHome($slug)` | `news:v1:news-by-category-home:{slug}` |
| `CacheKey::newsByCategory(string $slug, ?string $division = null, ?string $district = null, ?string $upazila = null, $date = null)` | `news:v1:news-by-category:{slug}[:div:{division}][:dist:{district}][:upa:{upazila}][:date:{date}]` |
| `CacheKey::newsByPrintCategory(string $slug, ?string $date = null)` | `news:v1:news-by-print-category:{slug}[:date:{date}]` |
| `CacheKey::webStorySliderDataHome()` | `news:v1:web-story-slider-data:home` |
| `CacheKey::webStorySliderDataSports()` | `news:v1:web-story-slider-data:sports` |
| `CacheKey::epaperQuizGrid(string $dateYmd)` | `news:v1:epaper-quiz:grid:{dateYmd}` |
| `CacheKey::epaperQuizPage(int $page, string $dateYmd)` | `news:v1:epaper-quiz:page:{page}:{dateYmd}` |
| `CacheKey::epaperQuizQuestion(int $questionId)` | `news:v1:epaper-quiz:question:{questionId}` |
| `CacheKey::epaperPublications()` | `news:v1:epaper-reader:publications` |
| `CacheKey::epaperReaderShow(string $slug, string $dateYmd, string $revisionKey)` | `news:v1:epaper-reader:show:{slug}:{dateYmd}:rev:{revisionKey}` (`revisionKey` is `"latest"` or a revision number as a string) |

Free-text parameters (tag slugs, division/district/upazila names, dates) are passed through `Str::slug()` before being appended to the key.

### Invalidating cache — `CacheManager`

`CacheManager` wraps the cache store configured via `shared-cache.store` and exposes one `forget*` method per key above, so invalidation logic doesn't need to know the underlying key format. Resolve it from the container:

```php
use Rakibmiah99\AgamirsomoySharedCache\CacheManager;

app(CacheManager::class)->forgetNewsDetails($slug);
```

Or inject it via the constructor/method:

```php
public function update(Request $request, CacheManager $cache, News $news)
{
    $news->update($request->validated());

    $cache->forgetNewsDetails($news->slug);
    $cache->forgetNewsByCategoryHome($news->category->slug);
    $cache->forgetSiteLatestNews();

    return redirect()->back();
}
```

Every `forget*` method mirrors a `CacheKey` builder 1:1 (e.g. `forgetHeaderTag()` ↔ `headerTag()`, `forgetMostReadNewsByCategory()` ↔ `mostReadNewsByCategory()`) and returns the boolean result of `Cache::forget()`.

## License

MIT
