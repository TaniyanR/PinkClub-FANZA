<?php

declare(strict_types=1);

$apiType = 'actresses';
$pageTitle = '女優 API設定';
$testButtonLabel = '女優を10件テスト取得';
$testRunner = static function (DmmApiClient $client): array {
    return $client->searchActresses(['hits' => 10, 'offset' => 1]);
};

require __DIR__ . '/api_settings_common.php';
