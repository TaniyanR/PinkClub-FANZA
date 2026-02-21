<?php
declare(strict_types=1);

if (!function_exists('config_get')) {
    require_once __DIR__ . '/config.php';
}

function dmm_api_empty_response(string $error = ''): array
{
    return [
        'ok' => false,
        'data' => [
            'result' => [
                'items' => [],
                'actress' => [],
            ],
        ],
        'http_code' => 0,
        'error' => $error,
        'is_cached' => false,
    ];
}

function dmm_api_cache_dir(): string
{
    return __DIR__ . '/../cache';
}

function dmm_api_cache_key(string $endpoint, array $params): string
{
    ksort($params);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return sha1($endpoint . '|' . $query);
}

function dmm_api_cache_read(string $path, int $ttl): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $mtime = filemtime($path);
    if ($mtime === false || (time() - $mtime) > $ttl) {
        return null;
    }

    $json = @file_get_contents($path);
    if ($json === false) {
        return null;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function dmm_api_cache_write(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            error_log("Failed to create cache directory: {$dir}");
            return;
        }
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        error_log("Cache directory is not writable: {$dir}");
        return;
    }

    $tmp = $path . '.tmp';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }

    $result = @file_put_contents($tmp, $json, LOCK_EX);
    if ($result === false) {
        return;
    }

    if (!@rename($tmp, $path)) {
        @unlink($tmp);
    }
}

function dmm_api_request(string $endpoint, array $params): array
{
    $requiredKeys = ['api_id', 'affiliate_id', 'site', 'service', 'floor'];
    foreach ($requiredKeys as $key) {
        $value = trim((string)($params[$key] ?? ''));
        if ($value === '') {
            $response = dmm_api_empty_response('missing_required');
            dmm_api_write_log($endpoint, 0, 'missing_required');
            return $response;
        }
    }

    $cacheKey = dmm_api_cache_key($endpoint, $params);
    $cachePath = dmm_api_cache_dir() . '/dmm_' . $cacheKey . '.json';
    $cacheTtl = 3600;

    $api = config_get('dmm_api', []);
    $connectTimeout = 10;
    $timeout = 20;

    if (is_array($api)) {
        $cacheTtlValue = filter_var($api['cache_ttl'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 60, 'max_range' => 86400],
        ]);
        if ($cacheTtlValue !== false) {
            $cacheTtl = $cacheTtlValue;
        }

        $connectTimeoutValue = filter_var($api['connect_timeout'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 30],
        ]);
        if ($connectTimeoutValue !== false) {
            $connectTimeout = $connectTimeoutValue;
        }

        $timeoutValue = filter_var($api['timeout'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 5, 'max_range' => 60],
        ]);
        if ($timeoutValue !== false) {
            $timeout = $timeoutValue;
        }
    }

    $baseUrl = 'https://api.dmm.com/affiliate/v3/';
    $url = $baseUrl . rawurlencode($endpoint) . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $attempts = 0;
    $lastResponse = dmm_api_empty_response('request_failed');

    while ($attempts < 2) {
        $attempts++;

        $ch = curl_init($url);
        if ($ch === false) {
            $lastResponse = dmm_api_empty_response('curl_init_failed');
            break;
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
            $lastResponse = dmm_api_empty_response($curlError !== '' ? $curlError : 'curl_error');
        } else {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $lastResponse = [
                    'ok' => $httpCode === 200,
                    'data' => $decoded,
                    'http_code' => $httpCode,
                    'error' => $httpCode === 200 ? '' : 'http_error',
                    'is_cached' => false,
                ];

                if ($httpCode === 200) {
                    dmm_api_cache_write($cachePath, $lastResponse);
                    dmm_api_write_log($endpoint, $httpCode, 'ok');
                    return $lastResponse;
                }
                dmm_api_write_log($endpoint, $httpCode, 'http_error');
            } else {
                $lastResponse = dmm_api_empty_response('invalid_json');
                $lastResponse['http_code'] = $httpCode;
                dmm_api_write_log($endpoint, $httpCode, 'invalid_json');
            }
        }

        if ($attempts < 2) {
            usleep(200000);
        }
    }

    $cached = dmm_api_cache_read($cachePath, $cacheTtl);
    if (is_array($cached) && isset($cached['data'])) {
        $cached['is_cached'] = true;
        return $cached;
    }

    dmm_api_write_log($endpoint, (int)($lastResponse['http_code'] ?? 0), (string)($lastResponse['error'] ?? 'request_failed'));
    return $lastResponse;
}

function dmm_api_log_path(): string
{
    return __DIR__ . '/../storage/logs/api.log';
}

function dmm_api_write_log(string $endpoint, int $statusCode, string $summary): void
{
    $dir = dirname(dmm_api_log_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = sprintf(
        "[%s] endpoint=%s status=%d summary=%s\n",
        date('Y-m-d H:i:s'),
        preg_replace('/[^A-Za-z0-9_\/-]/', '_', $endpoint) ?? 'unknown',
        $statusCode,
        preg_replace('/[^A-Za-z0-9_.:\- ]/', '_', $summary) ?? 'unknown'
    );
    @file_put_contents(dmm_api_log_path(), $line, FILE_APPEND);
}
