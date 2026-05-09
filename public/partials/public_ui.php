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

if (!function_exists('pcf_find_article_row_by_item')) {
    function pcf_find_article_row_by_item(array $item): ?array
    {
        $contentId = trim((string)($item['content_id'] ?? ''));
        $productId = trim((string)($item['product_id'] ?? ''));
        if ($contentId === '' && $productId === '') {
            return null;
        }

        try {
            $stmt = db()->prepare('SELECT title, image_url FROM articles WHERE product_id IN (?, ?) ORDER BY id DESC LIMIT 1');
            $stmt->execute([$contentId, $productId]);
            $row = $stmt->fetch();
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('pcf_find_item_image_from_duplicates')) {
    function pcf_find_item_image_from_duplicates(array $item): string
    {
        $contentId = trim((string)($item['content_id'] ?? ''));
        $productId = trim((string)($item['product_id'] ?? ''));
        if ($contentId === '' && $productId === '') {
            return '';
        }

        try {
            $sql = 'SELECT image_large, image_list, image_small, raw_json FROM items WHERE (content_id = :cid OR product_id = :pid) ORDER BY id DESC LIMIT 30';
            $stmt = db()->prepare($sql);
            $stmt->bindValue(':cid', $contentId, PDO::PARAM_STR);
            $stmt->bindValue(':pid', $productId, PDO::PARAM_STR);
            $stmt->execute();
            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $imageLarge = trim((string)($row['image_large'] ?? ''));
                if ($imageLarge !== '') {
                    return $imageLarge;
                }
                $imageList = pcf_parse_image_list_urls((string)($row['image_list'] ?? ''));
                if ($imageList !== []) {
                    return (string)$imageList[0];
                }
                $imageSmall = trim((string)($row['image_small'] ?? ''));
                if ($imageSmall !== '') {
                    return $imageSmall;
                }
                $rawJson = trim((string)($row['raw_json'] ?? ''));
                if ($rawJson !== '') {
                    $raw = json_decode($rawJson, true);
                    if (is_array($raw)) {
                        $fallback = trim((string)($raw['imageURL']['large'] ?? $raw['imageURL']['small'] ?? ''));
                        if ($fallback !== '') {
                            return $fallback;
                        }
                    }
                }
            }
        } catch (Throwable) {
        }

        return '';
    }
}

if (!function_exists('pcf_guess_package_image_urls')) {
    function pcf_guess_package_image_urls(array $item): array
    {
        $baseId = trim((string)($item['content_id'] ?? ''));
        if ($baseId === '') {
            $baseId = trim((string)($item['product_id'] ?? ''));
        }
        $baseId = strtolower(preg_replace('/[^a-z0-9]/i', '', $baseId) ?? '');
        if ($baseId === '') {
            return [];
        }

        return [
            'https://pics.dmm.co.jp/mono/movie/adult/' . $baseId . '/' . $baseId . 'pl.jpg',
            'https://pics.dmm.co.jp/mono/movie/adult/' . $baseId . '/' . $baseId . 'ps.jpg',
            'https://pics.dmm.co.jp/mono/movie/adult/' . $baseId . '/' . $baseId . 'jp.jpg',
        ];
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

        $articleRow = pcf_find_article_row_by_item($item);
        if (is_array($articleRow)) {
            $articleImage = trim((string)($articleRow['image_url'] ?? ''));
            if ($articleImage !== '') {
                return $articleImage;
            }
        }

        $fromDuplicates = pcf_find_item_image_from_duplicates($item);
        if ($fromDuplicates !== '') {
            return $fromDuplicates;
        }

        $guessed = pcf_guess_package_image_urls($item);
        if ($guessed !== []) {
            return (string)$guessed[0];
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
            foreach (pcf_guess_package_image_urls($item) as $guessedUrl) {
                $candidate = trim((string)$guessedUrl);
                if ($candidate !== '' && !in_array($candidate, $images, true)) {
                    $images[] = $candidate;
                }
                if (count($images) >= 2) {
                    break;
                }
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

        $articleRow = pcf_find_article_row_by_item($item);
        if (is_array($articleRow)) {
            $articleTitle = trim((string)($articleRow['title'] ?? ''));
            if ($articleTitle !== '') {
                return $articleTitle;
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
        $packageImageUrl = pcf_item_image($item);
        $packageLinkUrl = str_starts_with($packageImageUrl, 'data:image/svg+xml') ? $itemUrl : $packageImageUrl;

        $sampleImageUrl = $contentId !== ''
            ? public_url('sample_images.php?content_id=' . rawurlencode($contentId))
            : '';

        echo '<article class="card rail-card pcf-card pcf-item-card" style="width:100%;max-width:none;min-width:0;">';
        echo '<a class="pcf-item-card__thumb-link" href="' . e($packageLinkUrl) . '"' . ($packageLinkUrl !== $itemUrl ? ' target="_blank" rel="noopener noreferrer"' : '') . '>';
        echo '<span style="display:flex;gap:2px;">';
        echo '<img class="thumb pcf-item-card__thumb" src="' . e($imageA) . '" alt="' . e($title) . '" loading="lazy" style="width:50%;aspect-ratio:3/4;object-fit:cover;">';
        echo '<img class="thumb pcf-item-card__thumb" src="' . e($imageB) . '" alt="' . e($title) . '" loading="lazy" style="width:50%;aspect-ratio:3/4;object-fit:cover;">';
        echo '</span>';
        echo '</a>';

        echo '<a class="rail-card__title pcf-item-card__title" href="' . e($itemUrl) . '">' . e($title) . '</a>';

        echo '<div class="sample-buttons">';
        if ($sampleMovieUrl !== '') {
            echo '<a class="sample-button sample-button--enabled" href="' . e($sampleMovieUrl) . '" target="_blank" rel="noopener noreferrer">サンプル動画</a>';
        } else {
            echo '<span class="sample-button sample-button--disabled">サンプル動画</span>';
        }
        if ($sampleImageUrl !== '') {
            echo '<a class="sample-button sample-button--enabled" href="' . e($sampleImageUrl) . '">サンプル画像</a>';
        } else {
            echo '<span class="sample-button sample-button--disabled">サンプル画像</span>';
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
