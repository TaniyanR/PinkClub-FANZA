<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function dmm_api_cache_dir(): ?string
{
    $dir = __DIR__ . '/../cache';
    if (is_dir($dir)) {
        return $dir;
    }

    if (@mkdir($dir, 0755, true) || is_dir($dir)) {
        return $dir;
    }

    return null;
}

function dmm_api_cache_key(string $endpoint, array $params): string
{
    $keyParams = $params;
    $keyParams['_endpoint'] = $endpoint;
    ksort($keyParams);
    return sha1((string)json_encode($keyParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function dmm_api_cache_path(string $endpoint, array $params): ?string
{
    $dir = dmm_api_cache_dir();
    if ($dir === null) {
        return null;
    }

    $key = dmm_api_cache_key($endpoint, $params);
    return $dir . '/dmm_api_' . $key . '.json';
}

function dmm_api_load_cache(?string $path, int $ttl): ?array
{
    if ($path === null || !is_readable($path)) {
        return null;
    }

    $mtime = @filemtime($path);
    if ($mtime === false || (time() - $mtime) > $ttl) {
        return null;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function dmm_api_save_cache(?string $path, array $payload): void
{
    if ($path === null) {
        return;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        return;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }

    $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return;
    }

    @rename($tmp, $path);
}

function dmm_api_empty_data(string $endpoint): array
{
    $endpoint = strtolower($endpoint);
    if ($endpoint === 'itemlist') {
        return ['result' => ['status' => '200', 'items' => []]];
    }
    if ($endpoint === 'actresssearch') {
        return ['result' => ['status' => '200', 'actress' => []]];
    }
    return ['result' => ['status' => '200']];
}

function dmm_api_request_once(string $endpoint, array $params): array
{
    // configのdmm_apiをベースに不足があれば補う（params側が優先）
    $api = config_get('dmm_api', []);
    $connectTimeout = 10;
    $timeout = 20;
    if (is_array($api)) {
        $base = [
            'api_id' => (string)($api['api_id'] ?? ''),
            'affiliate_id' => (string)($api['affiliate_id'] ?? ''),
            'site' => (string)($api['site'] ?? ''),
            'service' => (string)($api['service'] ?? ''),
            'floor' => (string)($api['floor'] ?? ''),
        ];
        foreach ($base as $k => $v) {
            if (!array_key_exists($k, $params) || $params[$k] === '' || $params[$k] === null) {
                // site/service/floor も空なら補完（空のままだとAPI側で落ちる）
                $params[$k] = $v;
            }
        }

        $connectTimeout = (int)($api['connect_timeout'] ?? $connectTimeout);
        $timeout = (int)($api['timeout'] ?? $timeout);
    }
    if ($connectTimeout < 1 || $connectTimeout > 30) {
        $connectTimeout = 10;
    }
    if ($timeout < 5 || $timeout > 60) {
        $timeout = 20;
    }

    // 最低限必須（空ならAPIを叩かない）
    $required = ['api_id', 'affiliate_id', 'site', 'service', 'floor'];
    foreach ($required as $k) {
        if (empty($params[$k])) {
            return [
                'ok' => false,
                'http_code' => 0,
                'error' => 'Missing required param: ' . $k,
                'raw' => null,
                'data' => null,
            ];
        }
    }

    $endpoint = trim($endpoint);
    if ($endpoint === '') {
        return [
            'ok' => false,
            'http_code' => 0,
            'error' => 'Missing endpoint',
            'raw' => null,
            'data' => null,
        ];
    }

    $url = 'https://api.dmm.com/affiliate/v3/' . rawurlencode($endpoint);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $fullUrl = $url . '?' . $query;

    $ch = curl_init($fullUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => $timeout,                 // 応答全体
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,   // 接続
        CURLOPT_FAILONERROR => false,   // HTTPエラーでも本文を拾う
        CURLOPT_USERAGENT => 'PinkClub-FANZA/1.0 (+https://example.invalid)',
    ]);

    $raw = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        if (function_exists('log_message')) {
            log_message('API request failed: errno=' . $curlErrNo . ' error=' . $curlErr);
        }
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $curlErr !== '' ? $curlErr : ('cURL error ' . $curlErrNo),
            'raw' => null,
            'data' => null,
        ];
    }

    // HTTP非2xxはok=false（本文はrawに残す）
    if ($httpCode < 200 || $httpCode >= 300) {
        if (function_exists('log_message')) {
            log_message('API http error: ' . $httpCode . ' endpoint=' . $endpoint);
        }
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => 'HTTP ' . $httpCode,
            'raw' => $raw,
            'data' => null,
        ];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        if (function_exists('log_message')) {
            log_message('API response JSON decode failed: ' . substr($raw, 0, 500));
        }
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => 'Invalid JSON',
            'raw' => $raw,
            'data' => null,
        ];
    }

    return [
        'ok' => true,
        'http_code' => $httpCode,
        'error' => null,
        'raw' => $raw,
        'data' => $data,
    ];
}

function dmm_api_request(string $endpoint, array $params): array
{
    $cachePath = dmm_api_cache_path($endpoint, $params);
    $cached = dmm_api_load_cache($cachePath, 3600);

    $attempts = 0;
    $lastResponse = null;
    while ($attempts < 2) {
        $attempts++;
        $response = dmm_api_request_once($endpoint, $params);
        $lastResponse = $response;
        if (($response['ok'] ?? false) === true) {
            dmm_api_save_cache($cachePath, [
                'data' => $response['data'],
                'cached_at' => time(),
            ]);
            $response['is_cached'] = false;
            return $response;
        }

        if ($attempts < 2) {
            usleep(200000);
        }
    }

    if (is_array($cached) && isset($cached['data']) && is_array($cached['data'])) {
        return [
            'ok' => true,
            'http_code' => 200,
            'error' => $lastResponse['error'] ?? 'API failed',
            'raw' => null,
            'data' => $cached['data'],
            'is_cached' => true,
        ];
    }

    if (function_exists('log_message')) {
        $message = $lastResponse['error'] ?? 'API failed';
        log_message('API failed without cache: ' . $message);
    }

    return [
        'ok' => true,
        'http_code' => $lastResponse['http_code'] ?? 0,
        'error' => $lastResponse['error'] ?? 'API failed',
        'raw' => null,
        'data' => dmm_api_empty_data($endpoint),
        'is_cached' => false,
    ];
}
