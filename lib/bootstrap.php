<?php

declare(strict_types=1);

$config = require_once __DIR__ . '/../config/config.php';
if (!is_array($config)) {
    $config = [];
}
$GLOBALS['app_config'] = $config;

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionLifetime = (int)($config['security']['session_lifetime'] ?? 86400);
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    session_name($config['security']['session_name'] ?? 'pinkclub_fanza_session');
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    $requestScheme = strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? ''));
    $serverPort = (string)($_SERVER['SERVER_PORT'] ?? '');
    $isHttps = ($https !== '' && $https !== 'off' && $https !== '0')
        || $requestScheme === 'https'
        || $serverPort === '443';
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/installer.php';
require_once __DIR__ . '/paginator.php';
require_once __DIR__ . '/app.php';
