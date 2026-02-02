<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $message = sprintf('[%s] Uncaught exception: %s in %s:%d', date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine());
    if (function_exists('log_message')) {
        log_message($message);
    } else {
        @file_put_contents($dir . '/app.log', $message . "\n", FILE_APPEND);
    }
});

return [
    'site' => [
        'title' => 'PinkClub-F',
        // 例: 'https://example.com'（末尾スラッシュなし）
        'base_url' => '',
    ],

    'db' => [
        // DSN方式（PDOでそのまま使える）
        // ※ 本番の認証情報は config.local.php で上書き推奨
        'dsn' => 'mysql:host=127.0.0.1;dbname=pinkclub_f;charset=utf8mb4',
        'user' => 'root',
        'password' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // MySQL向け：本物のプリペアを使用
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    // DMM/FANZA API（認証情報は config.local.php で上書き前提）
    'dmm_api' => [
        'api_id' => '',
        'affiliate_id' => '',
        'site' => 'FANZA',
        'service' => 'digital',
        'floor' => 'videoa',
    ],

    'admin' => [
        'basic_user' => '',
        'basic_pass' => '',
    ],

    'security' => [
        // in.php のIP/UAハッシュに利用（本番は config.local.php で必ず上書き）
        'secret' => '',
    ],

    'rss' => [
        'base_items' => 5,
        'max_items' => 30,
        'access_window_hours' => 24,
        'access_per_item' => 20,
        'weight_new' => 0.6,
        'cache_ttl_seconds' => 60,
        'detection_refresh_hours' => 12,
        'dedupe_window_seconds' => 600,
        'log_retention_days' => 14,
    ],
];
