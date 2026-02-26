<?php

declare(strict_types=1);

$config = require_once __DIR__ . '/../config/config.php';
if (!is_array($config)) {
    $config = [];
}
$GLOBALS['app_config'] = $config;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['security']['session_name'] ?? 'pinkclub_fanza_session');
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
