<?php

declare(strict_types=1);

namespace Rakibmiah99\AgamirsomoySharedCache;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final class CacheKey
{
    private static function prefix(): string
    {
        return Config::get('shared-cache.prefix', 'news');
    }

    private static function version(): string
    {
        return Config::get('shared-cache.version', 'v1');
    }

    private static function timezone(): string
    {
        return Config::get('shared-cache.timezone', 'Asia/Dhaka');
    }

    private static function base(): string
    {
        return self::prefix() . ':' . self::version();
    }

    private static function today(): string
    {
        return Carbon::now(self::timezone())->format('Y-m-d');
    }

    /**
     * Canonical, deterministic key builder. Every named helper below is a thin
     * wrapper around this so all cache keys share one normalization strategy:
     * params are sorted by name (order-independent), null/empty values are
     * dropped, and string values are slugified. Two calls with the same
     * logical params — regardless of argument/query-string order — always
     * resolve to the same key.
     *
     * @param  array<string, scalar|null>  $params
     */
    public static function make(string $name, array $params = []): string
    {
        ksort($params);

        $segments = [];
        foreach ($params as $paramName => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized = is_string($value) ? Str::slug($value) : $value;
            $segments[] = $paramName . '=' . $normalized;
        }

        $suffix = $segments === [] ? '' : ':' . implode(':', $segments);

        return self::base() . ':' . $name . $suffix;
    }

    public static function header(): string
    {
        return self::base() . ':header:all';
    }

    public static function category(): string
    {
        return self::base() . ':category:all';
    }

    public static function marque(): string
    {
        return self::base() . ':marquee:all';
    }

    public static function breakingNews(): string
    {
        return self::base() . ':breaking:all';
    }

    public static function headerTag(string $tagSlug): string
    {
        return self::make('header:tag', ['slug' => $tagSlug]);
    }

    public static function divisions(): string
    {
        return self::base() . ':geo-location:divisions';
    }

    public static function districts(string $divisionSlug): string
    {
        return self::make('geo-location:districts', ['division' => $divisionSlug]);
    }

    public static function upazilas(string $districtSlug): string
    {
        return self::make('geo-location:upazilas', ['district' => $districtSlug]);
    }

    public static function homeSectionWiseNews(string $sectionName): string
    {
        return self::make('home-section-wise-news', ['section' => $sectionName]);
    }

    public static function siteLatestNews(?string $date = null): string
    {
        return self::make('site-latest-news', ['date' => $date ?? self::today()]);
    }

    public static function siteMostReadNews(?string $readDate = null): string
    {
        return self::make('site-most-read-news', ['date' => $readDate ?? self::today()]);
    }

    public static function mostReadNewsByCategory(int $categoryId, int $limit = 15): string
    {
        return self::make('most-read-by-category', ['category' => $categoryId, 'limit' => $limit]);
    }

    public static function newsDetails(string $slugKey): string
    {
        return self::make('news-details', ['slug' => $slugKey]);
    }

    public static function newsByCategoryHome(string $slug): string
    {
        return self::make('news-by-category-home', ['slug' => $slug]);
    }

    public static function newsByCategory(
        string $slug,
        ?string $division = null,
        ?string $district = null,
        ?string $upazila = null,
        mixed $date = null,
        ?int $page = null,
    ): string {
        return self::make('news-by-category', [
            'slug' => $slug,
            'div' => $division,
            'dist' => $district,
            'upa' => $upazila,
            'date' => $date,
            'page' => $page,
        ]);
    }

    public static function newsByPrintCategory(string $slug, ?string $date = null): string
    {
        return self::make('news-by-print-category', ['slug' => $slug, 'date' => $date]);
    }

    public static function newsByTag(string $tagSlug, ?int $page = null): string
    {
        return self::make('news-by-tag', ['slug' => $tagSlug, 'page' => $page]);
    }

    public static function newsByAuthor(int $authorId, ?int $page = null): string
    {
        return self::make('news-by-author', ['author' => $authorId, 'page' => $page]);
    }

    public static function relatedNews(int $newsId): string
    {
        return self::make('related-news', ['news' => $newsId]);
    }

    public static function newsTimeline(int $newsId): string
    {
        return self::make('news-timeline', ['news' => $newsId]);
    }

    public static function webStorySliderDataHome(): string
    {
        return self::base() . ':web-story-slider-data:home';
    }

    public static function webStorySliderDataSports(): string
    {
        return self::base() . ':web-story-slider-data:sports';
    }

    public static function epaperQuizGrid(string $dateYmd): string
    {
        return self::make('epaper-quiz:grid', ['date' => $dateYmd]);
    }

    public static function epaperQuizPage(int $page, string $dateYmd): string
    {
        return self::make('epaper-quiz:page', ['page' => $page, 'date' => $dateYmd]);
    }

    public static function epaperQuizQuestion(int $questionId): string
    {
        return self::make('epaper-quiz:question', ['id' => $questionId]);
    }

    public static function epaperPublications(): string
    {
        return self::base() . ':epaper-reader:publications';
    }

    /**
     * @param  string  $revisionKey  "latest" or concrete revision number as string
     */
    public static function epaperReaderShow(string $slug, string $dateYmd, string $revisionKey): string
    {
        return self::make('epaper-reader:show', ['slug' => $slug, 'date' => $dateYmd, 'rev' => $revisionKey]);
    }
}
