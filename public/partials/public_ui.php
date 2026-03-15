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
