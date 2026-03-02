<?php

declare(strict_types=1);

$apiType = 'genres';
$pageTitle = 'ジャンル API設定';
$testButtonLabel = 'ジャンルを10件テスト取得';
$testRunner = static function (DmmApiClient $client): array {
    $s = settings_get();
    return $client->searchGenres(['floor_id' => (string)$s['master_floor_id'], 'hits' => 10, 'offset' => 1]);
};

require __DIR__ . '/api_settings_common.php';
