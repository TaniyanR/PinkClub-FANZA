<?php

declare(strict_types=1);

if (!function_exists('image_fallback_url')) {
    function image_fallback_url(): string
    {
        return asset_url('img/no-image.png');
    }
}

if (!function_exists('pcf_placeholder_data_uri')) {
    function pcf_placeholder_data_uri(string $label = 'No Image'): string
    {
        $safeLabel = e($label);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="900" viewBox="0 0 640 900"><rect width="100%" height="100%" fill="#1b2434"/><text x="50%" y="50%" fill="#7f8ea3" font-size="34" font-family="sans-serif" font-weight="700" text-anchor="middle" dominant-baseline="middle">' . $safeLabel . '</text></svg>';
        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }
}

if (!function_exists('pcf_normalize_external_media_url')) {
    function pcf_normalize_external_media_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return '';
    }
}

if (!function_exists('pcf_is_self_hosted_fanza_image')) {
    function pcf_is_self_hosted_fanza_image(string $url): bool
    {
        $value = trim($url);
        if ($value === '') {
            return false;
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }

        if (!preg_match('#^/(?:uploads|images|img|cache|thumbnails|thumbs|wp-content/uploads)(?:/|$)#i', $path)) {
            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if ($host === null || $host === false || $host === '') {
            return str_starts_with($value, '/');
        }

        $siteHost = parse_url(public_url(''), PHP_URL_HOST);
        return is_string($siteHost) && $siteHost !== '' && strcasecmp($host, $siteHost) === 0;
    }
}

if (!function_exists('pcf_parse_image_urls')) {
    function pcf_parse_image_urls(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $trimmed = trim($value);
        if (($trimmed[0] ?? '') === '[' || ($trimmed[0] ?? '') === '{') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $urls = [];
                $stack = [$decoded];
                while ($stack !== []) {
                    $current = array_pop($stack);
                    if (is_string($current)) {
                        $normalized = pcf_normalize_external_media_url($current);
                        if ($normalized !== '') {
                            $urls[] = $normalized;
                        }
                        continue;
                    }
                    if (!is_array($current)) {
                        continue;
                    }
                    foreach ($current as $child) {
                        $stack[] = $child;
                    }
                }
                if ($urls !== []) {
                    return array_values(array_unique($urls));
                }
            }
        }

        $parts = preg_split('/[\r\n,|\s]+/', $trimmed);
        if (!is_array($parts)) {
            return [];
        }

        $urls = [];
        foreach ($parts as $part) {
            $normalized = pcf_normalize_external_media_url((string)$part);
            if ($normalized !== '') {
                $urls[] = $normalized;
            }
        }

        return array_values(array_unique($urls));
    }
}

if (!function_exists('pcf_item_raw_payload')) {
    function pcf_item_raw_payload(array $item): array
    {
        $raw = $item['raw_json'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('pcf_collect_sample_image_urls')) {
    function pcf_collect_sample_image_urls(mixed $value, array &$urls): void
    {
        if (is_string($value)) {
            foreach (pcf_parse_image_urls($value) as $candidate) {
                if (!pcf_is_self_hosted_fanza_image($candidate)) {
                    $urls[] = $candidate;
                }
            }
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $child) {
            pcf_collect_sample_image_urls($child, $urls);
        }
    }
}

if (!function_exists('pcf_item_sample_images')) {
    function pcf_item_sample_images(array $item): array
    {
        $images = [];

        if (isset($item['sample_images'])) {
            pcf_collect_sample_image_urls($item['sample_images'], $images);
        }

        $raw = pcf_item_raw_payload($item);
        $sampleImageUrl = $raw['sampleImageURL'] ?? null;
        if (is_array($sampleImageUrl)) {
            foreach (['sample_l', 'sample_s'] as $sampleKey) {
                $sampleImages = [];
                pcf_collect_sample_image_urls($sampleImageUrl[$sampleKey]['image'] ?? null, $sampleImages);
                if ($sampleImages !== []) {
                    $images = array_merge($images, $sampleImages);
                    break;
                }
            }
        } elseif ($sampleImageUrl !== null) {
            pcf_collect_sample_image_urls($sampleImageUrl, $images);
        }

        if ($images === [] && isset($item['image_list'])) {
            pcf_collect_sample_image_urls((string)$item['image_list'], $images);
        }

        return array_values(array_unique(array_filter($images, static function (string $url): bool {
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            return in_array($scheme, ['http', 'https'], true);
        })));
    }
}

if (!function_exists('pcf_collect_sample_movie_urls')) {
    function pcf_collect_sample_movie_urls(mixed $value, array &$urls): void
    {
        if (is_string($value)) {
            $normalized = pcf_normalize_external_media_url($value);
            if ($normalized !== '') {
                $urls[] = $normalized;
            }
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $child) {
            pcf_collect_sample_movie_urls($child, $urls);
        }
    }
}

if (!function_exists('pcf_item_sample_movie_urls')) {
    function pcf_item_sample_movie_urls(array $item): array
    {
        $urls = [];

        foreach ([
            'sample_movie_url_720',
            'sample_movie_url_644',
            'sample_movie_url_560',
            'sample_movie_url_476',
            'sample_movie_url',
            'sample_movie_url_pc',
        ] as $column) {
            if (isset($item[$column])) {
                pcf_collect_sample_movie_urls($item[$column], $urls);
            }
        }

        $raw = pcf_item_raw_payload($item);
        foreach (['sampleMovieURL', 'sample_movie_url', 'sampleMovieUrl', 'sampleMovieURLVR'] as $key) {
            if (array_key_exists($key, $raw)) {
                if (is_array($raw[$key])) {
                    foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                        pcf_collect_sample_movie_urls($raw[$key][$movieKey] ?? null, $urls);
                    }
                }
                pcf_collect_sample_movie_urls($raw[$key], $urls);
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }
}

if (!function_exists('pcf_item_sample_movie_url')) {
    function pcf_item_sample_movie_url(array $item): string
    {
        return pcf_item_sample_movie_urls($item)[0] ?? '';
    }
}

if (!function_exists('pcf_pick_detail_main_image')) {
    function pcf_pick_detail_main_image(array $item): string
    {
        foreach (['image_large', 'image_list', 'image_small', 'image_url'] as $key) {
            $values = $key === 'image_list'
                ? pcf_parse_image_urls((string)($item[$key] ?? ''))
                : [pcf_normalize_external_media_url((string)($item[$key] ?? ''))];

            foreach ($values as $value) {
                $candidate = trim((string)$value);
                if ($candidate !== '' && !pcf_is_self_hosted_fanza_image($candidate)) {
                    return $candidate;
                }
            }
        }

        foreach (pcf_item_sample_images($item) as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('pcf_item_sample_images_endpoint_url')) {
    function pcf_item_sample_images_endpoint_url(array $item, string $format = 'json'): string
    {
        $params = [];
        $contentId = trim((string)($item['content_id'] ?? ''));
        $id = (int)($item['id'] ?? 0);

        if ($contentId !== '') {
            $params['content_id'] = $contentId;
        }
        if ($id > 0) {
            $params['id'] = $id;
        }
        if ($format !== '') {
            $params['format'] = $format;
        }

        return public_url('sample_images.php') . ($params !== [] ? '?' . http_build_query($params) : '');
    }
}

if (!function_exists('pcf_normalize_items_for_public')) {
    function pcf_normalize_items_for_public(array $items): array
    {
        return array_values(array_filter($items, 'is_array'));
    }
}

if (!function_exists('item_sample_image_urls')) {
    function item_sample_image_urls(array $item): array
    {
        return pcf_item_sample_images($item);
    }
}

if (!function_exists('item_sample_movie_url')) {
    function item_sample_movie_url(array $item): ?string
    {
        $url = pcf_item_sample_movie_url($item);
        return $url !== '' ? $url : null;
    }
}
