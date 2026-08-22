<?php

declare(strict_types=1);

namespace Rakibmiah99\AgamirsomoySharedCache;

/**
 * @deprecated Use SharedCache instead. Kept for backward compatibility with
 * any code resolving CacheManager from the container; every method here is a
 * thin delegate to SharedCache::forget()/CacheKey.
 */
class CacheManager
{
    public function __construct(
        protected SharedCache $shared,
    ) {
    }

    public function forgetHeader(): bool
    {
        return $this->shared->forget(CacheKey::header());
    }

    public function forgetCategory(): bool
    {
        return $this->shared->forget(CacheKey::category());
    }

    public function forgetMarque(): bool
    {
        return $this->shared->forget(CacheKey::marque());
    }

    public function forgetBreakingNews(): bool
    {
        return $this->shared->forget(CacheKey::breakingNews());
    }

    public function forgetHeaderTag(string $tagSlug): bool
    {
        return $this->shared->forget(CacheKey::headerTag($tagSlug));
    }

    public function forgetDivisions(): bool
    {
        return $this->shared->forget(CacheKey::divisions());
    }

    public function forgetDistricts(string $divisionSlug): bool
    {
        return $this->shared->forget(CacheKey::districts($divisionSlug));
    }

    public function forgetUpazilas(string $districtSlug): bool
    {
        return $this->shared->forget(CacheKey::upazilas($districtSlug));
    }

    public function forgetHomeSectionWiseNews(string $sectionName): bool
    {
        return $this->shared->forget(CacheKey::homeSectionWiseNews($sectionName));
    }

    public function forgetSiteLatestNews(?string $date = null): bool
    {
        return $this->shared->forget(CacheKey::siteLatestNews($date));
    }

    public function forgetSiteMostReadNews(?string $readDate = null): bool
    {
        return $this->shared->forget(CacheKey::siteMostReadNews($readDate));
    }

    public function forgetMostReadNewsByCategory(int $categoryId, int $limit = 15): bool
    {
        return $this->shared->forget(CacheKey::mostReadNewsByCategory($categoryId, $limit));
    }

    public function forgetNewsDetails(string $slugKey): bool
    {
        return $this->shared->forget(CacheKey::newsDetails($slugKey));
    }

    public function forgetNewsByCategoryHome(string $slug): bool
    {
        return $this->shared->forget(CacheKey::newsByCategoryHome($slug));
    }

    public function forgetNewsByCategory(string $slug, ?string $division = null, ?string $district = null, ?string $upazila = null, mixed $date = null): bool
    {
        return $this->shared->forget(CacheKey::newsByCategory($slug, $division, $district, $upazila, $date));
    }

    public function forgetNewsByPrintCategory(string $slug, ?string $date = null): bool
    {
        return $this->shared->forget(CacheKey::newsByPrintCategory($slug, $date));
    }

    public function forgetWebStorySliderDataHome(): bool
    {
        return $this->shared->forget(CacheKey::webStorySliderDataHome());
    }

    public function forgetWebStorySliderDataSports(): bool
    {
        return $this->shared->forget(CacheKey::webStorySliderDataSports());
    }

    public function forgetEpaperQuizGrid(string $dateYmd): bool
    {
        return $this->shared->forget(CacheKey::epaperQuizGrid($dateYmd));
    }

    public function forgetEpaperQuizPage(int $page, string $dateYmd): bool
    {
        return $this->shared->forget(CacheKey::epaperQuizPage($page, $dateYmd));
    }

    public function forgetEpaperQuizQuestion(int $questionId): bool
    {
        return $this->shared->forget(CacheKey::epaperQuizQuestion($questionId));
    }

    public function forgetEpaperPublications(): bool
    {
        return $this->shared->forget(CacheKey::epaperPublications());
    }

    public function forgetEpaperReaderShow(string $slug, string $dateYmd, string $revisionKey): bool
    {
        return $this->shared->forget(CacheKey::epaperReaderShow($slug, $dateYmd, $revisionKey));
    }
}
