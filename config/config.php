<?php

declare(strict_types=1);

if (!defined('APP_NAME')) {
    define('APP_NAME', 'PinkClub FANZA');
}
if (!defined('BASE_URL')) {
    $rawBaseUrl = (string)(getenv('PINKCLUB_BASE_URL') ?: 'http://localhost/pinkclub-fanza');
    $parts = parse_url($rawBaseUrl);
    if ($parts === false) {
        $rawBaseUrl = 'http://localhost/pinkclub-fanza';
        $parts = parse_url($rawBaseUrl) ?: [];
    }

    $scheme = $parts['scheme'] ?? 'http';
    $host = $parts['host'] ?? 'localhost';
    $port = isset($parts['port']) ? ':' . (string)$parts['port'] : '';
    $path = isset($parts['path']) ? '/' . trim((string)$parts['path'], '/') : '';
    if ($path === '/') {
        $path = '';
    }

    define('BASE_URL', $scheme . '://' . $host . $port . $path);
}
if (!defined('LOGIN_PATH')) {
    define('LOGIN_PATH', '/public/login0718.php');
}
if (!defined('ADMIN_HOME_PATH')) {
    define('ADMIN_HOME_PATH', '/pinkclub-fanza/admin/index.php');
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
