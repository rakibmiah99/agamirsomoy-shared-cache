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
        return
            self::base() . ':header:tag:' . Str::slug($tagSlug);
    }

    public static function divisions(): string
    {
        return self::base() . ':geo-location:divisions';
    }

    public static function districts($divisionSlug): string
    {
        return self::base() . ':geo-location:districts:'.$divisionSlug;
    }

    public static function upazilas($districtSlug): string
    {
        return self::base() . ':geo-location:upazilas:'.$districtSlug;
    }

    public static function homeSectionWiseNews($sectionName): string
    {
        return self::base() . ':home-section-wise-news:'.$sectionName;
    }

    public static function siteLatestNews(?string $date = null): string
    {
        $date ??= self::today();

        return self::base() . ':site-latest-news:' . $date;
    }

    public static function siteMostReadNews(?string $readDate = null): string
    {
        $readDate ??= self::today();

        return self::base() . ':site-most-read-news:' . $readDate;
    }

    public static function mostReadNewsByCategory(int $categoryId, int $limit = 15): string
    {
        return self::base() . ':most-read-by-category:' . $categoryId . ':' . $limit;
    }

    public static function newsDetails($slug_key): string
    {
        return self::base() . ':news-details:'.$slug_key;
    }

    public static function newsByCategoryHome($slug): string
    {
        return self::base() . ':news-by-category-home:'.$slug;
    }

    public static function newsByCategory(string $slug, ?string $division = null, ?string $district = null, ?string $upazila = null, $date = null): string
    {
        $key = self::base() . ':news-by-category:' . $slug;
        if ($division !== null && $division !== '') {
            $key .= ':div:' . Str::slug($division);
        }
        if ($district !== null && $district !== '') {
            $key .= ':dist:' . Str::slug($district);
        }
        if ($upazila !== null && $upazila !== '') {
            $key .= ':upa:' . Str::slug($upazila);
        }
        if ($date !== null && $date !== '') {
            $key .= ':date:' . Str::slug($date);
        }

        return $key;
    }

    public static function newsByPrintCategory(string $slug, ?string $date = null): string
    {
        $key = self::base() . ':news-by-print-category:' . $slug;
        if ($date !== null && $date !== '') {
            $key .= ':date:' . Str::slug($date);
        }

        return $key;
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
        return self::base() . ':epaper-quiz:grid:' . $dateYmd;
    }

    public static function epaperQuizPage(int $page, string $dateYmd): string
    {
        return self::base() . ':epaper-quiz:page:' . $page . ':' . $dateYmd;
    }

    public static function epaperQuizQuestion(int $questionId): string
    {
        return self::base() . ':epaper-quiz:question:' . $questionId;
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
        return self::base() . ':epaper-reader:show:' . $slug . ':' . $dateYmd . ':rev:' . $revisionKey;
    }

}
