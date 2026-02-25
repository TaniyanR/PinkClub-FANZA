<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['security']['session_name']);
    session_start();
}

date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/paginator.php';
require_once __DIR__ . '/app.php';
