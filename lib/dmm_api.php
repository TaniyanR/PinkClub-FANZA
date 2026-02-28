<?php
declare(strict_types=1);

if (!function_exists('config_get')) {
    require_once __DIR__ . '/config.php';
}

function dmm_api_cache_dir(): string { return __DIR__ . '/../cache'; }

function dmm_api_cache_key(string $endpoint, array $params): string
{
    ksort($params);
    return sha1($endpoint . '|' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

function dmm_api_cache_read(string $key, int $ttl): ?array
{
    $path = dmm_api_cache_dir() . '/dmm_' . $key . '.json';
    if (!is_file($path)) return null;
    $mtime = filemtime($path);
    if ($mtime === false || (time() - $mtime) > $ttl) return null;
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function dmm_api_cache_write(string $key, array $payload): void
{
    $dir = dmm_api_cache_dir();
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $path = $dir . '/dmm_' . $key . '.json';
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function dmm_api_request_generic(string $endpoint, array $params = []): array
{
    $api = config_get('dmm_api', []);
    $ttl = 72 * 3600;
    $query = array_filter(array_merge($params, ['output' => 'json']), static fn($v) => $v !== null && $v !== '');
    $cacheKey = dmm_api_cache_key($endpoint, $query);

    $cached = dmm_api_cache_read($cacheKey, $ttl);
    if (is_array($cached)) {
        $cached['is_cached'] = true;
        dmm_api_write_log($endpoint, 200, 'cache', $query);
        return $cached;
    }

    $url = 'https://api.dmm.com/affiliate/v3/' . rawurlencode($endpoint) . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => (int)($api['connect_timeout'] ?? 10), CURLOPT_TIMEOUT => (int)($api['timeout'] ?? 20)]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = (string)curl_error($ch);
    curl_close($ch);

    if ($body === false || $body === null) {
        dmm_api_write_log($endpoint, $httpCode, $err !== '' ? $err : 'curl_error', $query);
        return ['ok' => false, 'data' => ['result' => ['items' => []]], 'http_code' => $httpCode, 'error' => $err, 'is_cached' => false];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        dmm_api_write_log($endpoint, $httpCode, 'invalid_json', $query);
        return ['ok' => false, 'data' => ['result' => ['items' => []]], 'http_code' => $httpCode, 'error' => 'invalid_json', 'is_cached' => false];
    }

    $resp = ['ok' => $httpCode === 200, 'data' => $decoded, 'http_code' => $httpCode, 'error' => $httpCode === 200 ? '' : 'http_error', 'is_cached' => false];
    if ($httpCode === 200) {
        dmm_api_cache_write($cacheKey, $resp);
        dmm_api_write_log($endpoint, $httpCode, 'ok', $query);
    } else {
        dmm_api_write_log($endpoint, $httpCode, 'http_error', $query);
    }
    return $resp;
}

function dmm_api_request(string $endpoint, array $params): array { return dmm_api_request_generic($endpoint, $params); }

function dmm_api_item_list(array $params): array { return dmm_api_request_generic('ItemList', $params); }
function dmm_api_genre_search(array $params): array { return dmm_api_request_generic('GenreSearch', $params); }
function dmm_api_maker_search(array $params): array { return dmm_api_request_generic('MakerSearch', $params); }
function dmm_api_series_search(array $params): array { return dmm_api_request_generic('SeriesSearch', $params); }
function dmm_api_author_search(array $params): array { return dmm_api_request_generic('AuthorSearch', $params); }

function dmm_api_log_path(): string { return __DIR__ . '/../storage/logs/api.log'; }

function dmm_api_write_log(string $endpoint, int $statusCode, string $summary, array $params = []): void
{
    $dir = dirname(dmm_api_log_path());
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $line = sprintf("[%s] endpoint=%s status=%d summary=%s params=%s\n", date('Y-m-d H:i:s'), $endpoint, $statusCode, $summary, json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @file_put_contents(dmm_api_log_path(), $line, FILE_APPEND);
}
