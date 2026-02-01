<?php
require_once __DIR__ . '/db.php';

function dmm_api_request(string $endpoint, array $params): array
{
    $url = 'https://api.dmm.com/affiliate/v3/' . $endpoint;
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $fullUrl = $url . '?' . $query;

    $ch = curl_init($fullUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        log_message('API request failed: ' . $error);
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $error,
            'raw' => null,
            'data' => null,
        ];
    }

    $data = json_decode($raw, true);
    if ($data === null) {
        log_message('API response JSON decode failed: ' . $raw);
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => 'Invalid JSON',
            'raw' => $raw,
            'data' => null,
        ];
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'error' => null,
        'raw' => $raw,
        'data' => $data,
    ];
}
