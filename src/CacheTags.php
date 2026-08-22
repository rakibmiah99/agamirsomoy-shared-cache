<?php

declare(strict_types=1);

namespace Rakibmiah99\AgamirsomoySharedCache;

use Illuminate\Support\Facades\Config;

/**
 * Canonical dependency-tag names. Every cached collection response is tagged
 * with the set of entities its payload depends on (news id, category id,
 * tag id, author id, section slug, ...). Invalidation flushes the tags for
 * whatever changed rather than deleting individual keys, so it is O(number
 * of affected dependencies), never a keyspace scan or a global flush.
 */
final class CacheTags
{
    private static function prefix(): string
    {
        return Config::get('shared-cache.prefix', 'news');
    }

    public static function news(int $newsId): string
    {
        return self::prefix() . ':tag:news:' . $newsId;
    }

    /**
     * Slug-keyed variant of news(). Used for the news-details endpoint,
     * which is cached by slug: the numeric news id isn't known until after
     * the cache-miss DB query runs, but the slug is known upfront from the
     * request, so tagging by slug lets that endpoint be tag-checked without
     * a DB round trip on a cache hit.
     */
    public static function newsBySlug(string $slug): string
    {
        return self::prefix() . ':tag:news-slug:' . $slug;
    }

    public static function category(int $categoryId): string
    {
        return self::prefix() . ':tag:category:' . $categoryId;
    }

    public static function categoryTree(): string
    {
        return self::prefix() . ':tag:category-tree';
    }

    /**
     * Slug-keyed variant of category(). Category listing pages (by-category,
     * by-category-home, by-print-category) are cached by slug, and the
     * numeric category id isn't known on a cache hit without an extra
     * lookup - tagging by slug avoids that lookup on the hot read path. The
     * write side (admin) resolves both id and slug once per mutation (cheap,
     * since writes are rare) and invalidates both tags.
     */
    public static function categoryBySlug(string $slug): string
    {
        return self::prefix() . ':tag:category-slug:' . $slug;
    }

    public static function tag(int $tagId): string
    {
        return self::prefix() . ':tag:tag:' . $tagId;
    }

    public static function author(int $authorId): string
    {
        return self::prefix() . ':tag:author:' . $authorId;
    }

    public static function section(string $sectionSlug): string
    {
        return self::prefix() . ':tag:section:' . $sectionSlug;
    }

    public static function header(): string
    {
        return self::prefix() . ':tag:header';
    }

    public static function marquee(): string
    {
        return self::prefix() . ':tag:marquee';
    }

    public static function breakingNews(): string
    {
        return self::prefix() . ':tag:breaking';
    }

    public static function latestNews(): string
    {
        return self::prefix() . ':tag:latest';
    }

    public static function mostRead(): string
    {
        return self::prefix() . ':tag:most-read';
    }

    public static function geo(): string
    {
        return self::prefix() . ':tag:geo';
    }

    public static function webStory(): string
    {
        return self::prefix() . ':tag:web-story';
    }

    public static function epaper(): string
    {
        return self::prefix() . ':tag:epaper';
    }

    /**
     * Build the many-tags → many-ids helper arrays used by invalidation
     * calls, e.g. CacheTags::many('category', [1, 2, 3]).
     *
     * @param  array<int|string>  $ids
     * @return list<string>
     */
    public static function many(string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id !== null && $id !== '')));

        return array_map(
            static fn ($id) => match ($type) {
                'news' => self::news((int) $id),
                'category' => self::category((int) $id),
                'tag' => self::tag((int) $id),
                'author' => self::author((int) $id),
                'section' => self::section((string) $id),
                'news-slug' => self::newsBySlug((string) $id),
                'category-slug' => self::categoryBySlug((string) $id),
                default => throw new \InvalidArgumentException("Unknown tag type [{$type}]"),
            },
            $ids
        );
    }
}
