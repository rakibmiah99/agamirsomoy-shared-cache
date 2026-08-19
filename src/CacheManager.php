<?php

declare(strict_types=1);

namespace Rakib\SharedCache;

use Illuminate\Contracts\Cache\Repository;

class CacheManager
{
    public function __construct(
        protected Repository $cache
    ) {
    }

    public function forgetHeader(): bool
    {
        return $this->cache->forget(
            CacheKey::header()
        );
    }

    public function forgetCategory(): bool
    {
        return $this->cache->forget(
            CacheKey::category()
        );
    }

    public function forgetMarque(): bool
    {
        return $this->cache->forget(
            CacheKey::marque()
        );
    }

    public function forgetBreakingNews(): bool
    {
        return $this->cache->forget(
            CacheKey::breakingNews()
        );
    }

    public function forgetHeaderTag(string $tagSlug): bool
    {
        return $this->cache->forget(
            CacheKey::headerTag($tagSlug)
        );
    }

    public function forgetDivisions(): bool
    {
        return $this->cache->forget(
            CacheKey::divisions()
        );
    }

    public function forgetDistricts($divisionSlug): bool
    {
        return $this->cache->forget(
            CacheKey::districts($divisionSlug)
        );
    }

    public function forgetUpazilas($districtSlug): bool
    {
        return $this->cache->forget(
            CacheKey::upazilas($districtSlug)
        );
    }

    public function forgetHomeSectionWiseNews($sectionName): bool
    {
        return $this->cache->forget(
            CacheKey::homeSectionWiseNews($sectionName)
        );
    }

    public function forgetSiteLatestNews(?string $date = null): bool
    {
        return $this->cache->forget(
            CacheKey::siteLatestNews($date)
        );
    }

    public function forgetSiteMostReadNews(?string $readDate = null): bool
    {
        return $this->cache->forget(
            CacheKey::siteMostReadNews($readDate)
        );
    }

    public function forgetMostReadNewsByCategory(int $categoryId, int $limit = 15): bool
    {
        return $this->cache->forget(
            CacheKey::mostReadNewsByCategory($categoryId, $limit)
        );
    }

    public function forgetNewsDetails($slugKey): bool
    {
        return $this->cache->forget(
            CacheKey::newsDetails($slugKey)
        );
    }

    public function forgetNewsByCategoryHome($slug): bool
    {
        return $this->cache->forget(
            CacheKey::newsByCategoryHome($slug)
        );
    }

    public function forgetNewsByCategory(string $slug, ?string $division = null, ?string $district = null, ?string $upazila = null, $date = null): bool
    {
        return $this->cache->forget(
            CacheKey::newsByCategory($slug, $division, $district, $upazila, $date)
        );
    }

    public function forgetNewsByPrintCategory(string $slug, ?string $date = null): bool
    {
        return $this->cache->forget(
            CacheKey::newsByPrintCategory($slug, $date)
        );
    }

    public function forgetWebStorySliderDataHome(): bool
    {
        return $this->cache->forget(
            CacheKey::webStorySliderDataHome()
        );
    }

    public function forgetWebStorySliderDataSports(): bool
    {
        return $this->cache->forget(
            CacheKey::webStorySliderDataSports()
        );
    }

    public function forgetEpaperQuizGrid(string $dateYmd): bool
    {
        return $this->cache->forget(
            CacheKey::epaperQuizGrid($dateYmd)
        );
    }

    public function forgetEpaperQuizPage(int $page, string $dateYmd): bool
    {
        return $this->cache->forget(
            CacheKey::epaperQuizPage($page, $dateYmd)
        );
    }

    public function forgetEpaperQuizQuestion(int $questionId): bool
    {
        return $this->cache->forget(
            CacheKey::epaperQuizQuestion($questionId)
        );
    }

    public function forgetEpaperPublications(): bool
    {
        return $this->cache->forget(
            CacheKey::epaperPublications()
        );
    }

    public function forgetEpaperReaderShow(string $slug, string $dateYmd, string $revisionKey): bool
    {
        return $this->cache->forget(
            CacheKey::epaperReaderShow($slug, $dateYmd, $revisionKey)
        );
    }
}
