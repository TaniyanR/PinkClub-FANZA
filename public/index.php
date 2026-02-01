<?php
declare(strict_types=1);

return [
    'site' => [
        'title' => 'PinkClub-F',
        // 例: 'https://example.com'（末尾スラッシュなし）
        'base_url' => '',
    ],

    'db' => [
        // DSN方式（PDOでそのまま使える）
        'dsn' => 'mysql:host=127.0.0.1;dbname=pinkclub_f;charset=utf8mb4',
        'user' => 'root',
        'password' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],

    // DMM/FANZA API（settings.phpで上書きしてもOKな前提）
    'dmm_api' => [
        'api_id' => '',
        'affiliate_id' => '',
        'site' => 'FANZA',
        'service' => 'digital',
        'floor' => 'videoa',
        'hits' => 20,
        'sort' => 'date',
        'output' => 'json',
    ],
];
