<?php

declare(strict_types=1);

$apiType = 'series';
$pageTitle = 'シリーズ API設定';
$testButtonLabel = 'シリーズを10件テスト取得';
$testRunner = static function (DmmApiClient $client): array {
    $s = settings_get();
    return $client->searchSeries(['floor_id' => (string)$s['master_floor_id'], 'hits' => 10, 'offset' => 1]);
};

require __DIR__ . '/api_settings_common.php';
