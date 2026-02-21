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

    'app' => [
        'env' => 'dev',
        'debug' => true,
    ],
    'site' => [
        'title' => 'PinkClub-FANZA',
        // 例: 'https://example.com'（末尾スラッシュなし）
        'base_url' => '',
    ],

    'db' => [
        // XAMPPローカル向けデフォルト（必要なら config.local.php で上書き）
        'host' => '127.0.0.1',
        'name' => 'pinkclub_fanza',
        'dsn' => 'mysql:host=127.0.0.1;dbname=pinkclub_fanza;charset=utf8mb4',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // MySQL向け：本物のプリペアを使用
            PDO::ATTR_EMULATE_PREPARES => false,
            // PDO 2014 対策: 未バッファクエリ起因の競合を防ぐ
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
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
        // password
        'password_hash' => '$2y$12$lrWdfz4sxTR6N3fvb/F5qeH/N1W0exdLVqgUbS7ZHEqo6DbZQqlSC',
    ],
];
