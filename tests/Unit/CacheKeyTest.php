<?php

use Rakibmiah99\AgamirsomoySharedCache\CacheKey;

it('builds deterministic keys independent of param insertion order', function () {
    $a = CacheKey::make('news-by-category', ['slug' => 'sports', 'div' => 'dhaka', 'page' => 2]);
    $b = CacheKey::make('news-by-category', ['page' => 2, 'slug' => 'sports', 'div' => 'dhaka']);

    expect($a)->toBe($b);
});

it('drops null and empty params so absent filters do not change the key', function () {
    $a = CacheKey::make('news-by-category', ['slug' => 'sports', 'div' => null, 'dist' => '']);
    $b = CacheKey::make('news-by-category', ['slug' => 'sports']);

    expect($a)->toBe($b);
});

it('produces different keys for different param values', function () {
    $a = CacheKey::make('news-by-category', ['slug' => 'sports', 'page' => 1]);
    $b = CacheKey::make('news-by-category', ['slug' => 'sports', 'page' => 2]);

    expect($a)->not->toBe($b);
});

it('includes the limit in the most-read-by-category key so different limits cannot collide', function () {
    $a = CacheKey::mostReadNewsByCategory(5, 10);
    $b = CacheKey::mostReadNewsByCategory(5, 15);

    expect($a)->not->toBe($b);
});

it('normalizes ?page=1&category=10&limit=20 vs ?category=10&limit=20&page=1 to the same key', function () {
    $paramsA = ['page' => 1, 'category' => 10, 'limit' => 20];
    $paramsB = ['category' => 10, 'limit' => 20, 'page' => 1];

    expect(CacheKey::make('news-listing', $paramsA))->toBe(CacheKey::make('news-listing', $paramsB));
});

it('slugifies string params consistently', function () {
    $a = CacheKey::newsByCategory('Sports News', 'Dhaka Division');
    $b = CacheKey::make('news-by-category', ['slug' => 'sports-news', 'div' => 'dhaka-division']);

    expect($a)->toBe($b);
});
