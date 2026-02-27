<?php

declare(strict_types=1);

$configuredBaseUrl = trim((string)getenv('BASE_URL'));

if ($configuredBaseUrl !== '') {
    $baseUrl = rtrim($configuredBaseUrl, '/');
} else {
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($baseDir === '' || $baseDir === '.') {
        $baseDir = '';
    }
    if (str_ends_with($baseDir, '/public')) {
        $baseDir = substr($baseDir, 0, -7);
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $baseUrl = rtrim("{$scheme}://{$host}{$baseDir}", '/');
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
