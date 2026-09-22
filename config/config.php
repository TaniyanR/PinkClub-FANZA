<?php

declare(strict_types=1);

$configuredBaseUrl = trim((string) getenv('BASE_URL'));

function normalize_configured_base_url(string $value): string
{
    $normalized = rtrim(trim($value), '/');
    if ($normalized === '') {
        return '';
    }

    // Strip common entry points and folders if someone accidentally sets BASE_URL to them.
    // Examples:
    // - https://example.com/index.php            => https://example.com
    // - https://example.com/public/index.php     => https://example.com
    // - https://example.com/admin               => https://example.com
    // - https://example.com/admin/index.php      => https://example.com
    // - https://example.com/public              => https://example.com
    // - https://example.com/login0718.php        => https://example.com
    $normalized = preg_replace(
        '#/(index\.php|login\.php|login0718\.php|admin/login\.php|admin(?:/index\.php)?|public(?:/index\.php)?)/*$#i',
        '',
        $normalized
    );
    if (!is_string($normalized)) {
        return '';
    }

    return rtrim($normalized, '/');
}

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
        // Apache may expose the rewritten robots endpoint as SCRIPT_NAME.
        '#/robots\.txt$#i',
        '#/index\.php(?:/.*)?$#i',
        '#/[^/]+\.php(?:/.*)?$#i',
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
        // robots.txt is a route, not an application installation directory.
        '#/robots\.txt$#i',
        '#/index\.php(?:/.*)?$#i',
        '#/[^/]+\.php(?:/.*)?$#i',
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

/**
 * Build a safe fallback URL when BASE_URL is not configured.
 * Production URLs never reflect an arbitrary Host header. Localhost and the
 * explicitly trusted staging host remain dynamic so tests do not redirect to
 * production while Host-header injection protection stays intact.
 */
function trusted_fallback_base_url(string $detectedPath): string
{
    $rawHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $parsed = $rawHost !== '' ? parse_url('http://' . $rawHost) : false;
    $host = is_array($parsed) ? strtolower(trim((string) ($parsed['host'] ?? ''), '[]')) : '';
    $port = is_array($parsed) && isset($parsed['port']) ? (int) $parsed['port'] : null;
    $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    $isTrustedStaging = $host === 'pinkclubfanza.bichi.xyz';

    if ($isLocal || $isTrustedStaging) {
        $requestScheme = strtolower(trim((string) ($_SERVER['REQUEST_SCHEME'] ?? '')));
        $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = ($requestScheme === 'https' || $forwardedProto === 'https' || $isHttps) ? 'https' : 'http';
        $displayHost = $host === '::1' ? '[::1]' : $host;
        if ($port !== null && $port >= 1 && $port <= 65535) {
            $displayHost .= ':' . $port;
        }
        return rtrim($scheme . '://' . $displayHost . $detectedPath, '/');
    }

    return rtrim('https://pinkclub-fanza.com' . $detectedPath, '/');
}

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = detect_base_path($scriptName);
if ($basePath === '') {
    $basePath = detect_base_path_from_request_uri((string) ($_SERVER['REQUEST_URI'] ?? ''));
}

if ($configuredBaseUrl !== '') {
    $baseUrl = apply_detected_path_to_base_url(
        normalize_configured_base_url($configuredBaseUrl),
        $basePath
    );
} else {
    $baseUrl = trusted_fallback_base_url($basePath);
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

$dbConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'dbname' => '',
    'user' => '',
    'pass' => '',
    'charset' => 'utf8mb4',
];
$localConfigPath = __DIR__ . '/../config.local.php';
if (is_file($localConfigPath)) {
    try {
        $localConfig = require $localConfigPath;
        if (is_array($localConfig) && isset($localConfig['db']) && is_array($localConfig['db'])) {
            $localDbConfig = $localConfig['db'];
            if (!isset($localDbConfig['dbname']) && isset($localDbConfig['name'])) {
                $localDbConfig['dbname'] = $localDbConfig['name'];
            }
            if (!isset($localDbConfig['pass']) && isset($localDbConfig['password'])) {
                $localDbConfig['pass'] = $localDbConfig['password'];
            }
            $dbConfig = array_replace($dbConfig, array_intersect_key($localDbConfig, $dbConfig));
        }
    } catch (Throwable $e) {
        $GLOBALS['config_local_error'] = $e->getMessage();
    }
}

return [
    'db' => $dbConfig,
    'security' => [
        'session_name' => 'pinkclub_fanza_session',
    ],
    'dmm' => [
        'endpoint' => 'https://api.dmm.com/affiliate/v3/',
        'site' => 'FANZA',
    ],
    'pagination' => [
        'per_page' => 32,
    ],
];
