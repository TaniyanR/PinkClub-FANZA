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

if (!function_exists('pcf_parse_image_list_urls')) {
    function pcf_parse_image_list_urls(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\r\n,\|\s]+/', $raw) ?: [];
        $urls = [];
        foreach ($parts as $part) {
            $u = trim((string)$part);
            if ($u !== '') {
                $urls[] = $u;
            }
        }
        return array_values(array_unique($urls));
    }
}

if (!function_exists('pcf_item_image')) {
    function pcf_item_image(array $item): string
    {
        $imageLarge = trim((string)($item['image_large'] ?? ''));
        if ($imageLarge !== '') {
            return $imageLarge;
        }

        $imageList = pcf_parse_image_list_urls((string)($item['image_list'] ?? ''));
        if ($imageList !== []) {
            return (string)$imageList[0];
        }

        $imageSmall = trim((string)($item['image_small'] ?? ''));
        if ($imageSmall !== '') {
            return $imageSmall;
        }

        $rawJson = trim((string)($item['raw_json'] ?? ''));
        if ($rawJson !== '') {
            $raw = json_decode($rawJson, true);
            if (is_array($raw)) {
                $fallback = trim((string)($raw['imageURL']['large'] ?? $raw['imageURL']['small'] ?? ''));
                if ($fallback !== '') {
                    return $fallback;
                }
                foreach (['package_image', 'packageImage', 'jacket', 'jacketImage', 'imageURLLarge', 'imageURLSmall'] as $rawKey) {
                    $candidate = trim((string)($raw[$rawKey] ?? ''));
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
                $sampleImage = $raw['sampleImageURL']['sample_l']['image'][0] ?? $raw['sampleImageURL']['sample_s']['image'][0] ?? '';
                $sampleImage = trim((string)$sampleImage);
                if ($sampleImage !== '') {
                    return $sampleImage;
                }
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

if (!function_exists('pcf_item_card_images')) {
    function pcf_item_card_images(array $item): array
    {
        $images = [];

        $primary = pcf_item_image($item);
        if ($primary !== '' && !str_starts_with($primary, 'data:image/svg+xml')) {
            $images[] = $primary;
        }

        $imageList = pcf_parse_image_list_urls((string)($item['image_list'] ?? ''));
        foreach ($imageList as $url) {
            $candidate = trim((string)$url);
            if ($candidate !== '' && !in_array($candidate, $images, true)) {
                $images[] = $candidate;
            }
            if (count($images) >= 2) {
                break;
            }
        }

        if (count($images) < 2) {
            $rawJson = trim((string)($item['raw_json'] ?? ''));
            if ($rawJson !== '') {
                $raw = json_decode($rawJson, true);
                if (is_array($raw)) {
                    foreach (['sample_l', 'sample_s'] as $sizeKey) {
                        $rows = $raw['sampleImageURL'][$sizeKey]['image'] ?? null;
                        if (is_array($rows)) {
                            foreach ($rows as $row) {
                                $candidate = trim((string)$row);
                                if ($candidate !== '' && !in_array($candidate, $images, true)) {
                                    $images[] = $candidate;
                                }
                                if (count($images) >= 2) {
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_slice($images, 0, 2);
    }
}

if (!function_exists('pcf_resolve_item_title')) {
    function pcf_resolve_item_title(array $item): string
    {
        $title = trim((string)($item['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $rawJson = trim((string)($item['raw_json'] ?? ''));
        if ($rawJson !== '') {
            $raw = json_decode($rawJson, true);
            if (is_array($raw)) {
                foreach (['title', 'name', 'productTitle'] as $key) {
                    $candidate = trim((string)($raw[$key] ?? ''));
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
            }
        }

        return 'タイトル未設定';
    }
}

if (!function_exists('pcf_render_item_card')) {
    function pcf_render_item_card(array $item): void
    {
        $title = pcf_resolve_item_title($item);
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
                        foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                            $candidate = trim((string)($sampleMovie[$movieKey] ?? ''));
                            if ($candidate !== '') {
                                $sampleMovieUrl = $candidate;
                                break;
                            }
                        }
                    } elseif (is_string($sampleMovie) && trim($sampleMovie) !== '') {
                        $sampleMovieUrl = trim($sampleMovie);
                    }
                }
            }
        }

        $cardImages = pcf_item_card_images($item);
        $imageA = $cardImages[0] ?? pcf_placeholder_data_uri('No Image');
        $imageB = $cardImages[1] ?? $imageA;

        $sampleImageUrl = $contentId !== ''
            ? public_url('sample_images.php?content_id=' . rawurlencode($contentId))
            : '';

        echo '<article class="pcf-item-card" style="background:#fff;border:1px solid #d7dce5;border-radius:6px;padding:8px;max-width:390px;">';
        echo '<a href="' . e($itemUrl) . '" style="display:flex;gap:4px;text-decoration:none;">';
        echo '<img src="' . e($imageA) . '" alt="' . e($title) . '" loading="lazy" style="display:block;width:calc(50% - 2px);aspect-ratio:3/4;object-fit:cover;border:1px solid #cfd6e3;">';
        echo '<img src="' . e($imageB) . '" alt="' . e($title) . '" loading="lazy" style="display:block;width:calc(50% - 2px);aspect-ratio:3/4;object-fit:cover;border:1px solid #cfd6e3;">';
        echo '</a>';

        echo '<a href="' . e($itemUrl) . '" style="display:block;margin-top:10px;margin-bottom:14px;color:#2f7ba8;font-weight:700;font-size:15px;line-height:1.4;text-decoration:none;">' . e($title) . '</a>';

        echo '<div class="sample-buttons" style="display:flex;flex-direction:column;gap:8px;">';
        if ($sampleMovieUrl !== '') {
            echo '<a class="sample-button sample-button--enabled" href="' . e($sampleMovieUrl) . '" target="_blank" rel="noopener noreferrer" style="display:block;background:#2a4fcc;color:#fff;text-align:center;padding:10px 12px;border-radius:4px;text-decoration:none;font-weight:700;">サンプル動画</a>';
        } else {
            echo '<span class="sample-button sample-button--disabled" style="display:block;background:#9aa3b2;color:#fff;text-align:center;padding:10px 12px;border-radius:4px;font-weight:700;">サンプル動画</span>';
        }
        if ($sampleImageUrl !== '') {
            echo '<a class="sample-button sample-button--enabled" href="' . e($sampleImageUrl) . '" style="display:block;background:#2a4fcc;color:#fff;text-align:center;padding:10px 12px;border-radius:4px;text-decoration:none;font-weight:700;">サンプル画像</a>';
        } else {
            echo '<span class="sample-button sample-button--disabled" style="display:block;background:#9aa3b2;color:#fff;text-align:center;padding:10px 12px;border-radius:4px;font-weight:700;">サンプル画像</span>';
        }
        echo '<a class="sample-button sample-button--enabled" href="' . e($itemUrl) . '" style="display:block;background:#2a4fcc;color:#fff;text-align:center;padding:10px 12px;border-radius:4px;text-decoration:none;font-weight:700;">詳細ページ</a>';
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
