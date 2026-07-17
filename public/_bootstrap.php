<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/access_analytics.php';
require_once __DIR__ . '/../lib/crawler_guard.php';
require_once __DIR__ . '/../lib/public_page_cache.php';

pcf_crawler_guard_check();

$publicScriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$longCachePublicPages = [
    'index.php',
    'items.php',
    'item.php',
    'search.php',
    'actresses.php',
    'actress.php',
    'genres.php',
    'genre.php',
    'makers.php',
    'maker.php',
    'series.php',
    'series_list.php',
    'series_detail.php',
    'series_one.php',
    'labels.php',
    'label.php',
    'authors.php',
    'author.php',
    'posts.php',
    'post.php',
    'page.php',
];
$publicPageCacheTtl = in_array($publicScriptName, $longCachePublicPages, true) ? 600 : 120;
pcf_public_page_cache_start($publicPageCacheTtl);
