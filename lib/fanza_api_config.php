<?php
declare(strict_types=1);

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

    $items = $result['items'] ?? null;
    if (!is_array($items)) {
        return [
            'ok' => false,
            'http_code' => (int)($response['http_code'] ?? 200),
            'error_type' => 'api_error',
            'message' => 'ItemListの result.items が配列ではありません。',
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
