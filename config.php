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
        'title' => 'PinkClub-FANZA',
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
        'username' => 'admin',
        'password_hash' => '$2y$12$aa6K4m5qZD3A998IJvAqeOwQH9dvtDjiMEdCn7cBWrOoJLBiZw9G6',
    ],
];
