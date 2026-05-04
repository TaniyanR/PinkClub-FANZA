<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if (!function_exists('pcf_placeholder_data_uri')) {
    function pcf_placeholder_data_uri(string $label = 'No Image'): string
    {
        $safeLabel = e($label);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="900" viewBox="0 0 640 900"><rect width="100%" height="100%" fill="#161821"/><rect x="24" y="24" width="592" height="852" rx="20" fill="#202534" stroke="#2e3750"/><text x="50%" y="50%" fill="#9ea8c7" font-size="34" text-anchor="middle" font-family="Arial, sans-serif">' . $safeLabel . '</text></svg>';
        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }
}

if (!function_exists('pcf_item_image')) {
    function pcf_item_image(array $item): string
    {
        foreach (['image_large', 'image_list', 'image_small'] as $key) {
            $value = trim((string)($item[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return pcf_placeholder_data_uri('No Image');
    }
}

if (!function_exists('pcf_render_hero')) {
    function pcf_render_hero(string $title, string $subtitle = ''): void
    {
        echo '<section class="pcf-hero">';
        echo '<h1 class="pcf-hero__title">' . e($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="pcf-hero__subtitle">' . e($subtitle) . '</p>';
        }
        echo '</section>';
    }
}

if (!function_exists('pcf_is_noise_name')) {
    function pcf_is_noise_name(string $name): bool
    {
        $v = mb_strtolower(trim($name), 'UTF-8');
        if ($v === '') {
            return true;
        }

        if (str_contains($v, 'http://') || str_contains($v, 'https://') || str_contains($v, 'www.')) {
            return true;
        }

        if (preg_match('/\.(com|net|jp|org|info|biz)(?:$|[^a-z])/i', $v)) {
            return true;
        }

        if (str_contains($v, '/')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('pcf_pick_oldest_item')) {
    function pcf_pick_oldest_item(array $items): ?array
    {
        $oldest = null;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($oldest === null) {
                $oldest = $item;
                continue;
            }

            $itemDate = trim((string)($item['release_date'] ?? ''));
            $oldestDate = trim((string)($oldest['release_date'] ?? ''));
            if ($itemDate !== '' && ($oldestDate === '' || strcmp($itemDate, $oldestDate) < 0)) {
                $oldest = $item;
                continue;
            }
            if ($itemDate === $oldestDate && (int)($item['id'] ?? 0) < (int)($oldest['id'] ?? 0)) {
                $oldest = $item;
            }
        }

        return $oldest;
    }
}

if (!function_exists('pcf_render_breadcrumbs')) {
    function pcf_render_breadcrumbs(array $items): void
    {
        if ($items === []) {
            return;
        }

        echo '<nav class="pcf-breadcrumb" aria-label="パンくず">';
        foreach ($items as $index => $item) {
            $label = (string)($item['label'] ?? '');
            $url = trim((string)($item['url'] ?? ''));
            $isLast = $index === array_key_last($items);
            echo '<span class="pcf-breadcrumb__item">';
            if (!$isLast && $url !== '') {
                echo '<a href="' . e($url) . '">' . e($label) . '</a>';
            } else {
                echo '<span>' . e($label) . '</span>';
            }
            echo '</span>';
        }
        echo '</nav>';
    }
}

if (!function_exists('pcf_render_item_card')) {
    function pcf_render_item_card(array $item): void
    {
        $title = trim((string)($item['title'] ?? 'タイトル未設定'));
        $releaseDate = trim((string)($item['release_date'] ?? ''));
        $priceText = trim((string)($item['price_min_text'] ?? ''));
        $contentId = trim((string)($item['content_id'] ?? ''));
        $itemUrl = $contentId !== ''
            ? public_url('item.php?cid=' . rawurlencode($contentId))
            : public_url('item.php?id=' . (int)($item['id'] ?? 0));
        $sampleMovieUrl = '';
        foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $movieColumn) {
            $candidate = trim((string)($item[$movieColumn] ?? ''));
            if ($candidate !== '') {
                $sampleMovieUrl = $candidate;
                break;
            }
        }
        if ($sampleMovieUrl === '') {
            $rawJson = (string)($item['raw_json'] ?? '');
            if ($rawJson !== '') {
                $raw = json_decode($rawJson, true);
                if (is_array($raw)) {
                    $sampleMovie = $raw['sampleMovieURL'] ?? null;
                    if (is_array($sampleMovie)) {
                        foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306', '720', '644', '560', '476', 'url', 'pc'] as $movieKey) {
                            $candidate = trim((string)($sampleMovie[$movieKey] ?? ''));
                            if ($candidate !== '') {
                                $sampleMovieUrl = $candidate;
                                break;
                            }
                        }
                        if ($sampleMovieUrl === '') {
                            $stack = [$sampleMovie];
                            while ($stack !== []) {
                                $current = array_pop($stack);
                                if (!is_array($current)) {
                                    continue;
                                }
                                foreach ($current as $child) {
                                    if (is_string($child)) {
                                        $candidate = trim($child);
                                        if ($candidate !== '' && (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://') || str_starts_with($candidate, '//'))) {
                                            $sampleMovieUrl = str_starts_with($candidate, '//') ? ('https:' . $candidate) : $candidate;
                                            break 2;
                                        }
                                        continue;
                                    }
                                    if (is_array($child)) {
                                        $stack[] = $child;
                                    }
                                }
                            }
                        }
                    } elseif (is_string($sampleMovie) && trim($sampleMovie) !== '') {
                        $sampleMovieUrl = trim($sampleMovie);
                    }
                }
            }
        }

        echo '<article class="card rail-card rail-card--180 pcf-card pcf-item-card">';
        echo '<a class="pcf-item-card__thumb-link" href="' . e($itemUrl) . '">';
        echo '<img class="thumb pcf-item-card__thumb" src="' . e(pcf_item_image($item)) . '" alt="' . e($title) . '" loading="lazy">';
        echo '</a>';
        echo '<a class="rail-card__title pcf-item-card__title" href="' . e($itemUrl) . '">' . e($title) . '</a>';
        echo '<ul class="pcf-item-card__meta">';
        if ($releaseDate !== '') {
            echo '<li>発売日: ' . e(format_date($releaseDate)) . '</li>';
        }
        if ($priceText !== '') {
            echo '<li>価格: ' . e($priceText) . '</li>';
        }
        echo '</ul>';
        echo '<div class="sample-buttons">';
        if ($sampleMovieUrl !== '') {
            echo '<a class="sample-button sample-button--enabled" href="' . e($sampleMovieUrl) . '" target="_blank" rel="noopener noreferrer">サンプル動画</a>';
        } else {
            echo '<span class="sample-button sample-button--disabled">サンプル動画</span>';
        }
        echo '<a class="sample-button sample-button--enabled" href="' . e($itemUrl) . '">詳細ページ</a>';
        echo '</div>';
        echo '</article>';
    }
}


if (!function_exists('pcf_render_taxonomy_card')) {
    function pcf_render_taxonomy_card(string $name, string $url, mixed $count = null): void
    {
        $title = trim($name);
        if ($title === '') {
            $title = '名称未設定';
        }
        if (pcf_is_noise_name($title)) {
            return;
        }

        echo '<article class="pcf-card pcf-list-card pcf-taxonomy-card">';
        echo '<h3 class="pcf-list-card__title pcf-taxonomy-card__title">' . e($title) . '</h3>';
        if ($count !== null && (string)$count !== '' && (int)$count > 0) {
            echo '<div class="pcf-list-card__meta">作品数: ' . e((string)$count) . '</div>';
        }
        echo '<p><a class="pcf-btn" href="' . e($url) . '">詳細を見る</a></p>';
        echo '</article>';
    }
}

if (!function_exists('pcf_render_empty')) {
    function pcf_render_empty(string $message): void
    {
        echo '<div class="pcf-empty">' . e($message) . '</div>';
    }
}

if (!function_exists('pcf_render_pagination')) {
    function pcf_render_pagination(array $pg, string $path, array $extraQuery = []): void
    {
        $page = (int)($pg['page'] ?? 1);
        $pages = (int)($pg['pages'] ?? 1);
        if ($pages <= 1) {
            return;
        }

        echo '<nav class="pcf-pagination" aria-label="ページネーション">';
        for ($i = 1; $i <= $pages; $i++) {
            $query = $extraQuery;
            $query['page'] = $i;
            $url = $path . '?' . http_build_query($query);
            $class = 'pcf-pagination__link' . ($i === $page ? ' is-current' : '');
            echo '<a class="' . e($class) . '" href="' . e($url) . '">' . e((string)$i) . '</a>';
        }
        echo '</nav>';
    }
}
