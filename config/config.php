<?php

declare(strict_types=1);

$configuredBaseUrl = trim((string) getenv('BASE_URL'));

/**
 * Resolve application base path from the current script location.
 *
 * Examples:
 * - /pinkclub-fanza/public/index.php                => /pinkclub-fanza
 * - /pinkclub-fanza/admin/index.php                 => /pinkclub-fanza
 */
function detect_base_path(string $scriptName): string
{
    $normalized = str_replace('\\', '/', $scriptName);
    if ($normalized === '' || $normalized === '/') {
        return '';
    }

    $patterns = [
        '#/(?:public|admin)(?:/.*)?$#i',
        '#/index\.php(?:/.*)?$#i',
    ];

    foreach ($patterns as $pattern) {
        $candidate = preg_replace($pattern, '', $normalized);
        if (is_string($candidate) && $candidate !== $normalized) {
            $normalized = $candidate;
            break;
        }
    }

    $normalized = rtrim($normalized, '/');
    if ($normalized === '' || $normalized === '.') {
        return '';
    }

    return $normalized;
}

/**
 * Some servers expose SCRIPT_NAME as `/index.php` even when the app runs from a
 * subdirectory (e.g. `/pinkclub-fanza/public/`). In that case infer the base
 * path from REQUEST_URI.
 */
function detect_base_path_from_request_uri(string $requestUri): string
{
    $path = (string) parse_url($requestUri, PHP_URL_PATH);
    if ($path === '' || $path === '/') {
        return '';
    }

    $normalized = str_replace('\\', '/', $path);
    $patterns = [
        '#/(?:public|admin)(?:/.*)?$#i',
        '#/index\.php(?:/.*)?$#i',
    ];

    foreach ($patterns as $pattern) {
        $candidate = preg_replace($pattern, '', $normalized);
        if (is_string($candidate) && $candidate !== $normalized) {
            $normalized = $candidate;
            break;
        }
    }

    $normalized = rtrim($normalized, '/');
    if ($normalized === '' || $normalized === '.') {
        return '';
    }

    return $normalized;
}

/**
 * If BASE_URL is configured without a path (e.g. https://example.com),
 * and we detected the app is running under a subdirectory (e.g. /pinkclub-fanza),
 * append the detected path. If BASE_URL already contains a non-root path,
 * do not modify it.
 */
function apply_detected_path_to_base_url(string $configuredUrl, string $detectedPath): string
{
    $trimmed = rtrim($configuredUrl, '/');
    if ($trimmed === '' || $detectedPath === '') {
        return $trimmed;
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts)) {
        return $trimmed;
    }

    $configuredPath = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
    if ($configuredPath !== '' && $configuredPath !== '/') {
        return $trimmed; // already has a path
    }

    return $trimmed . $detectedPath;
}

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = detect_base_path($scriptName);
if ($basePath === '') {
    $basePath = detect_base_path_from_request_uri((string) ($_SERVER['REQUEST_URI'] ?? ''));
}

if ($configuredBaseUrl !== '') {
    $baseUrl = apply_detected_path_to_base_url($configuredBaseUrl, $basePath);
} else {
    $requestScheme = trim((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
    if ($requestScheme !== '') {
        $scheme = $requestScheme;
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $baseUrl = rtrim("{$scheme}://{$host}{$basePath}", '/');
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'PinkClub FANZA');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}
if (!defined('LOGIN_PATH')) {
    define('LOGIN_PATH', '/public/login0718.php');
}
if (!defined('ADMIN_HOME_PATH')) {
    define('ADMIN_HOME_PATH', '/admin/index.php');
}

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'pinkclub_fanza',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_name' => 'pinkclub_fanza_session',
    ],
    'dmm' => [
        'endpoint' => 'https://api.dmm.com/affiliate/v3/',
        'site' => 'FANZA',
    ],
    'pagination' => [
        'per_page' => 20,
    ],
];