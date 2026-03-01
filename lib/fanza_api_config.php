<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/repository.php';

function fanza_floor_definitions(): array
{
    return [
        // 動画
        'digital:videoa' => ['label' => 'FANZA（アダルト）- 動画 - ビデオ', 'service' => 'digital', 'floor' => 'videoa'],
        'digital:videoc' => ['label' => 'FANZA（アダルト）- 動画 - 成人映画', 'service' => 'digital', 'floor' => 'videoc'],
        'digital:amateur' => ['label' => 'FANZA（アダルト）- 動画 - 素人', 'service' => 'digital', 'floor' => 'amateur'],
        'digital:nikkatsu' => ['label' => 'FANZA（アダルト）- 動画 - 日活', 'service' => 'digital', 'floor' => 'nikkatsu'],
        'digital:anime' => ['label' => 'FANZA（アダルト）- 動画 - アニメ', 'service' => 'digital', 'floor' => 'anime'],

        // 月額動画
        'monthly:monthlies' => ['label' => 'FANZA（アダルト）- 月額動画 - 見放題ch', 'service' => 'monthly', 'floor' => 'monthlies'],

        // 通販
        'mono:dvd' => ['label' => 'FANZA（アダルト）- 通販 - DVD', 'service' => 'mono', 'floor' => 'dvd'],
        'mono:book' => ['label' => 'FANZA（アダルト）- 通販 - 大人向け書籍', 'service' => 'mono', 'floor' => 'book'],
        'mono:anime' => ['label' => 'FANZA（アダルト）- 通販 - アニメ', 'service' => 'mono', 'floor' => 'anime'],
        'mono:pcgame' => ['label' => 'FANZA（アダルト）- 通販 - PCゲーム', 'service' => 'mono', 'floor' => 'pcgame'],
        'mono:hobby' => ['label' => 'FANZA（アダルト）- 通販 - ホビー', 'service' => 'mono', 'floor' => 'hobby'],

        // 同人
        'doujin:doujin' => ['label' => 'FANZA（アダルト）- 同人 - 同人', 'service' => 'doujin', 'floor' => 'doujin'],

        // FANZAブックス
        'book:comic' => ['label' => 'FANZA（アダルト）- FANZAブックス - コミック', 'service' => 'book', 'floor' => 'comic'],
        'book:novel' => ['label' => 'FANZA（アダルト）- FANZAブックス - 小説', 'service' => 'book', 'floor' => 'novel'],
        'book:photo' => ['label' => 'FANZA（アダルト）- FANZAブックス - 写真集', 'service' => 'book', 'floor' => 'photo'],

        // PCゲーム
        'pcgame:pcgame' => ['label' => 'FANZA（アダルト）- PCゲーム - アダルトPCゲーム', 'service' => 'pcgame', 'floor' => 'pcgame'],

        // TODO: 追加フロアは service/floor の正確なコードが確認できた時点でここに追加する
        // （表示・復元・保存バリデーションがすべてこの定義を正本として参照する）
    ];
}

function fanza_default_floor_pair(): string
{
    return 'digital:videoa';
}

function fanza_find_floor_pair_by_service_floor(string $service, string $floor): ?string
{
    $service = trim($service);
    $floor = trim($floor);
    if ($service === '' || $floor === '') {
        return null;
    }

    $definitions = fanza_floor_definitions();
    foreach ($definitions as $pair => $definition) {
        if (
            (string)($definition['service'] ?? '') === $service
            && (string)($definition['floor'] ?? '') === $floor
        ) {
            return (string)$pair;
        }
    }

    return null;
}

function fanza_parse_floor_pair(string $pair): ?array
{
    $pair = trim($pair);
    if ($pair === '') {
        return null;
    }

    $definitions = fanza_floor_definitions();
    if (!isset($definitions[$pair])) {
        return null;
    }

    return [
        'pair' => $pair,
        'service' => $definitions[$pair]['service'],
        'floor' => $definitions[$pair]['floor'],
    ];
}

function fanza_floor_options_for_select(): array
{
    $options = [];
    foreach (fanza_floor_definitions() as $pair => $definition) {
        $options[(string)$pair] = (string)($definition['label'] ?? $pair);
    }

    return $options;
}

function fanza_resolve_floor_pair(?string $pairValue, ?string $serviceValue, ?string $floorValue): array
{
    $parsedPair = fanza_parse_floor_pair((string)$pairValue);
    if (is_array($parsedPair)) {
        $parsedPair['valid'] = true;
        return $parsedPair;
    }

    $legacyPair = fanza_find_floor_pair_by_service_floor((string)$serviceValue, (string)$floorValue);
    if ($legacyPair !== null) {
        $parsedLegacy = fanza_parse_floor_pair($legacyPair);
        if (is_array($parsedLegacy)) {
            $parsedLegacy['valid'] = true;
            return $parsedLegacy;
        }
    }

    $definitions = fanza_floor_definitions();
    $defaultPair = fanza_default_floor_pair();
    return [
        'pair' => $defaultPair,
        'service' => $definitions[$defaultPair]['service'],
        'floor' => $definitions[$defaultPair]['floor'],
        'valid' => false,
    ];
}

function fanza_normalize_api_config(array $apiConfig): array
{
    $resolved = fanza_resolve_floor_pair(null, (string)($apiConfig['service'] ?? ''), (string)($apiConfig['floor'] ?? ''));

    $apiConfig['site'] = 'FANZA';
    $apiConfig['service'] = $resolved['service'];
    $apiConfig['floor'] = $resolved['floor'];
    $apiConfig['floor_pair'] = $resolved['pair'];

    return $apiConfig;
}

function fanza_api_timeout_config(?array $apiConfig = null): array
{
    $connectTimeout = 10;
    $timeout = 20;

    if (is_array($apiConfig)) {
        $connectTimeoutValue = filter_var($apiConfig['connect_timeout'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 30],
        ]);
        if ($connectTimeoutValue !== false) {
            $connectTimeout = $connectTimeoutValue;
        }

        $timeoutValue = filter_var($apiConfig['timeout'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 5, 'max_range' => 60],
        ]);
        if ($timeoutValue !== false) {
            $timeout = $timeoutValue;
        }
    }

    return [
        'connect_timeout' => $connectTimeout,
        'timeout' => $timeout,
    ];
}

function fanza_api_json_snippet(string $body): string
{
    $body = trim($body);
    if ($body === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($body, 0, 300);
    }

    return substr($body, 0, 300);
}

function fanza_api_http_request(string $endpoint, array $params, int $connectTimeout, int $timeout): array
{
    $url = 'https://api.dmm.com/affiliate/v3/' . rawurlencode($endpoint)
        . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'http_code' => 0,
            'error_type' => 'curl_init_failed',
            'message' => 'cURL初期化に失敗しました。',
            'data' => null,
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FAILONERROR => false,
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error_type' => 'http_error',
            'message' => $curlError !== '' ? $curlError : 'HTTPリクエストに失敗しました。',
            'data' => null,
        ];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $snippet = fanza_api_json_snippet($body);
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error_type' => 'json_parse_error',
            'message' => 'JSONパースに失敗しました。' . ($snippet !== '' ? (' 応答抜粋: ' . $snippet) : ''),
            'data' => null,
        ];
    }

    if ($httpCode !== 200) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error_type' => 'http_error',
            'message' => 'HTTPステータスが200ではありません。',
            'data' => $decoded,
        ];
    }

    return [
        'ok' => true,
        'http_code' => $httpCode,
        'error_type' => '',
        'message' => '',
        'data' => $decoded,
    ];
}

function fanza_test_api_credentials(string $apiId, string $affiliateId, int $connectTimeout, int $timeout): array
{
    $response = fanza_api_http_request('FloorList', [
        'api_id' => $apiId,
        'affiliate_id' => $affiliateId,
        'output' => 'json',
    ], $connectTimeout, $timeout);

    if (!($response['ok'] ?? false)) {
        return $response;
    }

    $site = $response['data']['result']['site'] ?? null;
    if ($site === null) {
        return [
            'ok' => false,
            'http_code' => (int)($response['http_code'] ?? 200),
            'error_type' => 'api_error',
            'message' => 'FloorListの result.site が見つかりません。',
            'data' => $response['data'] ?? null,
        ];
    }

    return [
        'ok' => true,
        'http_code' => (int)($response['http_code'] ?? 200),
        'error_type' => '',
        'message' => 'FloorList取得成功',
        'data' => $response['data'] ?? null,
    ];
}

function fanza_test_item_fetch(string $apiId, string $affiliateId, string $service, string $floor, int $connectTimeout, int $timeout): array
{
    $response = fanza_api_http_request('ItemList', [
        'api_id' => $apiId,
        'affiliate_id' => $affiliateId,
        'site' => 'FANZA',
        'service' => $service,
        'floor' => $floor,
        'hits' => 10,
        'sort' => 'date',
        'output' => 'json',
    ], $connectTimeout, $timeout);

    if (!($response['ok'] ?? false)) {
        $response['service'] = $service;
        $response['floor'] = $floor;
        return $response;
    }

    $result = $response['data']['result'] ?? null;
    $status = is_array($result) ? ($result['status'] ?? null) : null;
    if ((string)$status !== '200') {
        return [
            'ok' => false,
            'http_code' => (int)($response['http_code'] ?? 200),
            'error_type' => 'api_error',
            'message' => 'ItemListの result.status が200ではありません。',
            'data' => $response['data'] ?? null,
            'service' => $service,
            'floor' => $floor,
        ];
    }

    $items = fanza_extract_items_from_itemlist_result(is_array($result) ? $result : []);
    if ($items === []) {
        return [
            'ok' => false,
            'http_code' => (int)($response['http_code'] ?? 200),
            'error_type' => 'api_error',
            'message' => 'ItemListの result.items が見つかりません。',
            'data' => $response['data'] ?? null,
            'service' => $service,
            'floor' => $floor,
        ];
    }

    return [
        'ok' => true,
        'http_code' => (int)($response['http_code'] ?? 200),
        'error_type' => '',
        'message' => '',
        'data' => $response['data'] ?? null,
        'service' => $service,
        'floor' => $floor,
        'item_count' => count($items),
    ];
}

function fanza_normalize_iteminfo_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    if (isset($value[0]) && is_array($value[0])) {
        return $value;
    }

    return [$value];
}

function fanza_collect_movie_urls(mixed $value, array &$urls): void
{
    if (is_string($value)) {
        $candidate = trim($value);
        if ($candidate !== '' && (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://'))) {
            $urls[] = $candidate;
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $child) {
        fanza_collect_movie_urls($child, $urls);
    }
}

function fanza_pick_sample_movie_urls(array $item): array
{
    $rawMovie = $item['sampleMovieURL'] ?? [];
    if (!is_array($rawMovie)) {
        $rawMovie = [];
    }

    $movie476 = trim((string)($rawMovie['size_476_306'] ?? ''));
    $movie560 = trim((string)($rawMovie['size_560_360'] ?? ''));
    $movie644 = trim((string)($rawMovie['size_644_414'] ?? ''));
    $movie720 = trim((string)($rawMovie['size_720_480'] ?? ''));

    if ($movie476 === '' && $movie560 === '' && $movie644 === '' && $movie720 === '') {
        $urls = [];
        fanza_collect_movie_urls($rawMovie, $urls);
        if ($urls !== []) {
            $movie720 = $urls[0];
        }
    }

    return [
        'sample_movie_url_476' => $movie476,
        'sample_movie_url_560' => $movie560,
        'sample_movie_url_644' => $movie644,
        'sample_movie_url_720' => $movie720,
    ];
}

function fanza_extract_items_from_itemlist_result(array $result): array
{
    $items = $result['items'] ?? null;
    if (!is_array($items)) {
        return [];
    }

    if (isset($items['item'])) {
        $wrapped = $items['item'];
        if (!is_array($wrapped)) {
            return [];
        }
        return isset($wrapped[0]) ? $wrapped : [$wrapped];
    }

    return isset($items[0]) ? $items : [$items];
}

function fanza_fetch_itemlist_for_sync(string $apiId, string $affiliateId, string $service, string $floor, int $connectTimeout, int $timeout, int $hits = 10): array
{
    $response = fanza_api_http_request('ItemList', [
        'api_id' => $apiId,
        'affiliate_id' => $affiliateId,
        'site' => 'FANZA',
        'service' => $service,
        'floor' => $floor,
        'hits' => max(1, min(100, $hits)),
        'sort' => 'date',
        'output' => 'json',
    ], $connectTimeout, $timeout);

    if (!($response['ok'] ?? false)) {
        return [
            'ok' => false,
            'error_type' => (string)($response['error_type'] ?? 'http_error'),
            'reason' => (string)($response['message'] ?? 'APIリクエストに失敗しました。'),
            'http_status' => (int)($response['http_code'] ?? 0),
            'api_status' => '',
            'body_excerpt' => '',
            'items' => [],
        ];
    }

    $result = $response['data']['result'] ?? null;
    $status = is_array($result) ? ($result['status'] ?? null) : null;
    if ((string)$status !== '200') {
        return [
            'ok' => false,
            'error_type' => 'api_response_error',
            'reason' => 'ItemList result.status が200ではありません。',
            'http_status' => (int)($response['http_code'] ?? 200),
            'api_status' => (string)$status,
            'body_excerpt' => fanza_api_json_snippet((string)json_encode($response['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'items' => [],
        ];
    }

    $items = fanza_extract_items_from_itemlist_result(is_array($result) ? $result : []);
    return [
        'ok' => true,
        'error_type' => '',
        'reason' => '',
        'http_status' => (int)($response['http_code'] ?? 200),
        'api_status' => (string)$status,
        'body_excerpt' => '',
        'items' => $items,
    ];
}

function fanza_log_sync_result(array $params, array $summary): void
{
    try {
        if (!db_table_exists(db(), 'api_logs')) {
            return;
        }
        $stmt = db()->prepare(
            'INSERT INTO api_logs (created_at, endpoint, params_json, status, http_code, item_count, error_message, success)
             VALUES (NOW(), :endpoint, :params_json, :status, :http_code, :item_count, :error_message, :success)'
        );
        $stmt->execute([
            ':endpoint' => 'ItemList(sync)',
            ':params_json' => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => !empty($summary['sync_ok']) ? 'success' : 'error',
            ':http_code' => (int)($summary['http_status'] ?? 0),
            ':item_count' => (int)($summary['fetched_items_count'] ?? 0),
            ':error_message' => !empty($summary['sync_ok']) ? null : (string)($summary['reason'] ?? 'unknown error'),
            ':success' => !empty($summary['sync_ok']) ? 1 : 0,
        ]);
    } catch (Throwable $e) {
        error_log('fanza_log_sync_result failed: ' . $e->getMessage());
    }
}

function fanza_sync_items_to_db(array $apiConfig, int $hits = 10): array
{
    $apiId = trim((string)($apiConfig['api_id'] ?? ''));
    $affiliateId = trim((string)($apiConfig['affiliate_id'] ?? ''));
    $resolvedFloor = fanza_resolve_floor_pair((string)($apiConfig['floor_pair'] ?? ''), (string)($apiConfig['service'] ?? ''), (string)($apiConfig['floor'] ?? ''));
    $service = (string)$resolvedFloor['service'];
    $floor = (string)$resolvedFloor['floor'];

    $hasApiLogsTable = db_table_exists('api_logs');

    $summary = [
        'sync_ok' => false,
        'target_floor_label' => (string)(fanza_floor_options_for_select()[$resolvedFloor['pair']] ?? ($service . ':' . $floor)),
        'target_service_code' => $service,
        'target_floor_code' => $floor,
        'service' => $service,
        'floor' => $floor,
        'http_status' => 0,
        'api_status' => '',
        'fetched_items_count' => 0,
        'saved_items_count' => 0,
        'saved_actresses_count' => 0,
        'saved_makers_count' => 0,
        'saved_genres_count' => 0,
        'saved_labels_count' => 0,
        'warnings' => [],
        'error_type' => '',
        'reason' => '',
    ];

    if (!$hasApiLogsTable) {
        $summary['warnings'][] = 'api_logs テーブルが無いため、APIログ保存をスキップしました。';
    }

    if ($apiId === '' || $affiliateId === '') {
        $summary['error_type'] = 'config_error';
        $summary['reason'] = 'API ID または アフィリエイトID が未設定です。';
        return $summary;
    }

    $timeouts = fanza_api_timeout_config($apiConfig);
    $fetchResult = fanza_fetch_itemlist_for_sync($apiId, $affiliateId, $service, $floor, $timeouts['connect_timeout'], $timeouts['timeout'], $hits);
    $summary['http_status'] = (int)($fetchResult['http_status'] ?? 0);

    $summary['api_status'] = (string)($fetchResult['api_status'] ?? '');

    if (!($fetchResult['ok'] ?? false)) {
        $summary['error_type'] = (string)($fetchResult['error_type'] ?? 'api_error');
        $summary['reason'] = (string)($fetchResult['reason'] ?? 'APIレスポンスの検証に失敗しました。');
        if (($fetchResult['body_excerpt'] ?? '') !== '') {
            $summary['warnings'][] = '応答抜粋: ' . (string)$fetchResult['body_excerpt'];
        }
        fanza_log_sync_result(['service' => $service, 'floor' => $floor, 'hits' => $hits], $summary);
        return $summary;
    }

    $items = is_array($fetchResult['items'] ?? null) ? $fetchResult['items'] : [];
    $summary['fetched_items_count'] = count($items);

    $savedActresses = [];
    $savedMakers = [];
$savedGenres = [];
    $savedLabels = [];

    try {
        $pdo = db();
        $hasItemLabelsTable = db_table_exists($pdo, 'item_labels');
        if (!$hasItemLabelsTable) {
            $summary['warnings'][] = 'item_labels テーブルが無いため、レーベル保存をスキップしました。';
        }

        $pdo->beginTransaction();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $contentId = trim((string)($item['content_id'] ?? $item['product_id'] ?? ''));
            if ($contentId === '') {
                continue;
            }

            $price = $item['prices']['price'] ?? ($item['price'] ?? null);
            $datePublished = isset($item['date']) ? (string)$item['date'] : null;
            if (is_string($datePublished) && strlen($datePublished) === 10) {
                $datePublished .= ' 00:00:00';
            }

            $movieUrls = fanza_pick_sample_movie_urls($item);

            $result = upsert_item([
                'content_id' => $contentId,
                'product_id' => (string)($item['product_id'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'url' => (string)($item['URL'] ?? ''),
                'affiliate_url' => (string)($item['affiliateURL'] ?? ''),
                'image_list' => (string)($item['imageURL']['list'] ?? ''),
                'image_small' => (string)($item['imageURL']['small'] ?? ''),
                'image_large' => (string)($item['imageURL']['large'] ?? ''),
                'sample_movie_url_476' => $movieUrls['sample_movie_url_476'],
                'sample_movie_url_560' => $movieUrls['sample_movie_url_560'],
                'sample_movie_url_644' => $movieUrls['sample_movie_url_644'],
                'sample_movie_url_720' => $movieUrls['sample_movie_url_720'],
                'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'date_published' => $datePublished,
                'service_code' => (string)($item['service_code'] ?? $service),
                'floor_code' => (string)($item['floor_code'] ?? $floor),
                'category_name' => (string)($item['category_name'] ?? ''),
                'price_min' => is_numeric($price) ? (int)$price : null,
            ]);
            if (($result['status'] ?? '') === 'inserted' || ($result['status'] ?? '') === 'updated') {
                $summary['saved_items_count']++;
            }

            $itemInfo = is_array($item['iteminfo'] ?? null) ? $item['iteminfo'] : [];

            $actressIds = [];
            foreach (fanza_normalize_iteminfo_list($itemInfo['actress'] ?? []) as $actress) {
                if (!is_array($actress)) { continue; }
                $actressId = (int)($actress['id'] ?? 0);
                $name = trim((string)($actress['name'] ?? ''));
                if ($actressId <= 0 || $name === '') { continue; }
                upsert_actress([
                    'id' => $actressId,
                    'name' => $name,
                    'ruby' => $actress['ruby'] ?? null,
                    'image_small' => $actress['imageURL']['small'] ?? null,
                    'image_large' => $actress['imageURL']['large'] ?? null,
                    'listurl_digital' => $actress['listURL']['digital'] ?? null,
                    'listurl_monthly' => $actress['listURL']['monthly'] ?? null,
                    'listurl_mono' => $actress['listURL']['mono'] ?? null,
                ]);
                $actressIds[] = $actressId;
                $savedActresses[$actressId] = true;
            }
            replace_item_relations($contentId, $actressIds, 'item_actresses', 'actress_id');

            $makerIds = [];
            foreach (fanza_normalize_iteminfo_list($itemInfo['maker'] ?? []) as $maker) {
                if (!is_array($maker)) { continue; }
                $makerId = (int)($maker['id'] ?? 0);
                $name = trim((string)($maker['name'] ?? ''));
                if ($makerId <= 0 || $name === '') { continue; }
                upsert_taxonomy('makers', 'id', [
                    'id' => $makerId,
                    'name' => $name,
                    'ruby' => $maker['ruby'] ?? null,
                    'list_url' => $maker['list_url'] ?? null,
                    'site_code' => $maker['site_code'] ?? null,
                    'service_code' => $maker['service_code'] ?? $service,
                    'floor_id' => $maker['floor_id'] ?? null,
                    'floor_code' => $maker['floor_code'] ?? $floor,
                ]);
                $makerIds[] = $makerId;
                $savedMakers[$makerId] = true;
            }
            replace_item_relations($contentId, $makerIds, 'item_makers', 'maker_id');

            $genreIds = [];
            foreach (fanza_normalize_iteminfo_list($itemInfo['genre'] ?? []) as $genre) {
                if (!is_array($genre)) { continue; }
                $genreId = (int)($genre['id'] ?? 0);
                $name = trim((string)($genre['name'] ?? ''));
                if ($genreId <= 0 || $name === '') { continue; }
                upsert_taxonomy('genres', 'id', [
                    'id' => $genreId,
                    'name' => $name,
                    'ruby' => $genre['ruby'] ?? null,
                    'list_url' => $genre['list_url'] ?? null,
                    'site_code' => $genre['site_code'] ?? null,
                    'service_code' => $genre['service_code'] ?? $service,
                    'floor_id' => $genre['floor_id'] ?? null,
                    'floor_code' => $genre['floor_code'] ?? $floor,
                ]);
                $genreIds[] = $genreId;
                $savedGenres[$genreId] = true;
            }
            replace_item_relations($contentId, $genreIds, 'item_genres', 'genre_id');

            if ($hasItemLabelsTable) {
                $labels = [];
                foreach (fanza_normalize_iteminfo_list($itemInfo['label'] ?? []) as $label) {
                    if (!is_array($label)) { continue; }
                    $labelName = trim((string)($label['name'] ?? ''));
                    if ($labelName === '') { continue; }
                    $labelId = is_numeric($label['id'] ?? null) ? (int)$label['id'] : null;
                    $labels[] = ['id' => $labelId, 'name' => $labelName, 'ruby' => $label['ruby'] ?? null];
                    $savedLabels[$labelId !== null ? (string)$labelId : ('name:' . strtolower($labelName))] = true;
                }
                replace_item_labels($contentId, $labels);
            }
        }

        $summary['saved_actresses_count'] = count($savedActresses);
        $summary['saved_makers_count'] = count($savedMakers);
        $summary['saved_genres_count'] = count($savedGenres);
        $summary['saved_labels_count'] = count($savedLabels);

        $pdo->commit();
        $summary['sync_ok'] = true;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $summary['saved_items_count'] = 0;
        $summary['saved_actresses_count'] = 0;
        $summary['saved_makers_count'] = 0;
        $summary['saved_genres_count'] = 0;
        $summary['saved_labels_count'] = 0;
        $summary['error_type'] = 'db_save_error';
        $summary['reason'] = $e->getMessage();
        $summary['warnings'][] = 'DB保存中に例外が発生したため、トランザクションをロールバックしました。';
    }

    fanza_log_sync_result(['service' => $service, 'floor' => $floor, 'hits' => $hits], $summary);
    return $summary;
}
