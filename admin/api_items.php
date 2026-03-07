<?php

declare(strict_types=1);

$apiType = 'items';
$pageTitle = '商品情報 API設定';
$testButtonLabel = '商品情報を10件テスト取得';
$testRunner = static function (DmmApiClient $client): array {
    $s = settings_get();
    return $client->fetchItems((string)$s['site'], (string)$s['service'], (string)$s['floor'], ['hits' => 10, 'offset' => 1]);
};

require __DIR__ . '/api_settings_common.php';
