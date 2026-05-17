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

if (!function_exists('pcf_parse_image_urls')) {
    function pcf_parse_image_urls(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed !== '' && $trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }
        }

        $parts = preg_split('/[\r\n,|\s]+/', $value);
        if (!is_array($parts)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $parts), static fn(string $v): bool => $v !== ''));
    }
}

if (!function_exists('pcf_first_image_from_mixed')) {
    function pcf_first_image_from_mixed(mixed $value): string
    {
        if (is_string($value)) {
            foreach (pcf_parse_image_urls($value) as $candidate) {
                $v = trim((string)$candidate);
                if ($v !== '') {
                    return $v;
                }
            }
            return '';
        }
        if (!is_array($value)) {
            return '';
        }
        foreach ($value as $child) {
            if (is_string($child) && trim($child) !== '') {
                return trim($child);
            }
            if (is_array($child)) {
                foreach (['url', 'src', 'value'] as $k) {
                    if (isset($child[$k]) && is_string($child[$k]) && trim($child[$k]) !== '') {
                        return trim((string)$child[$k]);
                    }
                }
            }
        }
        return '';
    }
}

if (!function_exists('pcf_item_image')) {
    function pcf_item_image(array $item): string
    {
        $candidates = [
            (string)($item['full_package_url'] ?? ''),
            (string)($item['main_image_url'] ?? ''),
            (string)($item['image_url'] ?? ''),
            (string)($item['image_large'] ?? ''),
            (string)($item['image_small'] ?? ''),
            (string)($item['package_image_large'] ?? ''),
            (string)($item['package_image_small'] ?? ''),
        ];

        $rawJson = (string)($item['raw_json'] ?? '');
        if ($rawJson !== '') {
            $raw = json_decode($rawJson, true);
            if (is_array($raw)) {
                $candidates[] = (string)($raw['packageImage']['large'] ?? '');
                $candidates[] = (string)($raw['packageImage']['small'] ?? '');
                $candidates[] = (string)($raw['imageURL']['large'] ?? '');
                $candidates[] = (string)($raw['imageURL']['small'] ?? '');
                $candidates[] = pcf_first_image_from_mixed($raw['imageURL']['list'] ?? null);
            }
        }

        foreach ($candidates as $candidate) {
            $value = trim($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        foreach (pcf_parse_image_urls((string)($item['image_list'] ?? '')) as $image) {
            $value = trim((string)$image);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('pcf_item_title')) {
    function pcf_item_title(array $item): string
    {
        $raw = [];
        $rawJson = (string)($item['raw_json'] ?? '');
        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        $candidates = [
            (string)($item['title'] ?? ''),
            (string)($raw['title'] ?? ''),
            (string)($raw['name'] ?? ''),
            (string)($raw['productTitle'] ?? ''),
            (string)($raw['iteminfo']['title'][0]['name'] ?? ''),
            (string)($raw['iteminfo']['title'][0]['value'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $value = trim($candidate);
            if ($value !== '') {
                return $value;
            }
        }
        return 'タイトル未設定';
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
    function pcf_render_item_card(array $item, int $width = 180, bool $preferFullPackageImage = false): void
    {
        $title = pcf_item_title($item);
        $contentId = trim((string)($item['content_id'] ?? ''));
        if ($contentId === '') {
            $raw = [];
            $rawJson = (string)($item['raw_json'] ?? '');
            if ($rawJson !== '') {
                $decoded = json_decode($rawJson, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            }
            $contentId = trim((string)($raw['content_id'] ?? ''));
        }
        $itemId = (int)($item['id'] ?? 0);
        $itemUrl = $itemId > 0 ? public_url('item.php?id=' . $itemId) : public_url('item.php?cid=' . rawurlencode($contentId));
        $imageUrl = trim(pcf_item_image($item));
        $sampleMovieUrl = '';
        foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $movieColumn) {
            $candidate = trim((string)($item[$movieColumn] ?? ''));
            if ($candidate !== '') {
                $sampleMovieUrl = $candidate;
                break;
            }
        }

        $sampleImagesUrl = public_url('sample_images.php?content_id=' . rawurlencode($contentId));
        $hasSampleImages = false;
        $rawJson = (string)($item['raw_json'] ?? '');
        if ($rawJson !== '') {
            $raw = json_decode($rawJson, true);
            if (is_array($raw)) {
                $sampleImageURL = $raw['sampleImageURL'] ?? null;
                if (is_array($sampleImageURL)) {
                    foreach (['sample_l', 'sample_s'] as $sampleKey) {
                        $images = $sampleImageURL[$sampleKey]['image'] ?? null;
                        if (is_array($images)) {
                            foreach ($images as $image) {
                                if (trim((string)$image) !== '') {
                                    $hasSampleImages = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        echo '<article class="pcf-dm-card">';
        echo '<a class="pcf-dm-card__image-link" href="' . e($itemUrl) . '">';
        if ($imageUrl !== '') {
            echo '<img class="pcf-dm-card__image" src="' . e($imageUrl) . '" alt="' . e($title) . '" loading="lazy">';
        } else {
            echo '<div class="pcf-dm-card__no-image">No Image</div>';
        }
        echo '</a>';
        echo '<h3 class="pcf-dm-card__title"><a href="' . e($itemUrl) . '">' . e($title) . '</a></h3>';
        echo '<div class="pcf-dm-card__actions">';
        if ($sampleMovieUrl !== '') {
            echo '<button type="button" class="pcf-dm-card__button sample-movie-trigger" data-movie-url="' . e($sampleMovieUrl) . '" data-movie-title="' . e($title) . '">サンプル動画</button>';
        } else {
            echo '<span class="pcf-dm-card__button is-disabled">サンプル動画</span>';
        }
        if ($hasSampleImages && $contentId !== '') {
            echo '<button type="button" class="pcf-dm-card__button" onclick="window.open(\'' . e($sampleImagesUrl) . '\',\'_blank\',\'noopener,noreferrer,width=760,height=540\');">サンプル画像</button>';
        } else {
            echo '<span class="pcf-dm-card__button is-disabled">サンプル画像</span>';
        }
        echo '<a class="pcf-dm-card__button" href="' . e($itemUrl) . '">詳細ページ</a>';
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