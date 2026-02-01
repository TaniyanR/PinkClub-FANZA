<?php

declare(strict_types=1);

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/db.php';

$api = config_get('dmm_api', []);

$requiredKeys = ['api_id', 'affiliate_id'];
foreach ($requiredKeys as $key) {
    if (empty($api[$key]) || str_contains($api[$key], 'YOUR_')) {
        fwrite(STDERR, "config.php または config.local.php の dmm_api.{$key} を設定してください。\n");
        exit(1);
    }
}

$query = http_build_query([
    'api_id' => $api['api_id'],
    'affiliate_id' => $api['affiliate_id'],
    'site' => $api['site'],
    'service' => $api['service'],
    'floor' => $api['floor'],
    'hits' => $api['hits'],
    'sort' => $api['sort'],
    'output' => $api['output'],
]);

$url = 'https://api.dmm.com/affiliate/v3/ItemList?' . $query;

$response = file_get_contents($url);
if ($response === false) {
    fwrite(STDERR, "APIリクエストに失敗しました。\n");
    exit(1);
}

$data = json_decode($response, true);
if (!is_array($data) || empty($data['result']['items'])) {
    fwrite(STDERR, "APIレスポンスが想定外です。\n");
    exit(1);
}

$pdo = db();
$pdo->beginTransaction();

$insert = $pdo->prepare(
    'INSERT INTO articles (product_id, title, description, affiliate_url, image_url, release_date, price, created_at, updated_at) '
    . 'VALUES (:product_id, :title, :description, :affiliate_url, :image_url, :release_date, :price, NOW(), NOW()) '
    . 'ON DUPLICATE KEY UPDATE '
    . 'title = VALUES(title), description = VALUES(description), affiliate_url = VALUES(affiliate_url), image_url = VALUES(image_url), release_date = VALUES(release_date), price = VALUES(price), updated_at = NOW()'
);

$count = 0;
foreach ($data['result']['items'] as $item) {
    $productId = $item['content_id'] ?? $item['product_id'] ?? null;
    if (!$productId) {
        continue;
    }

    $imageUrl = null;
    if (!empty($item['imageURL']['large'])) {
        $imageUrl = $item['imageURL']['large'];
    } elseif (!empty($item['imageURL']['list'])) {
        $imageUrl = $item['imageURL']['list'];
    }

    $releaseDate = null;
    if (!empty($item['date'])) {
        $releaseDate = substr($item['date'], 0, 10);
    }

    $price = null;
    if (isset($item['prices']['price'])) {
        $price = (int) $item['prices']['price'];
    }

    $insert->execute([
        ':product_id' => $productId,
        ':title' => $item['title'] ?? 'Untitled',
        ':description' => $item['comment'] ?? '',
        ':affiliate_url' => $item['affiliateURL'] ?? '',
        ':image_url' => $imageUrl ?? '',
        ':release_date' => $releaseDate,
        ':price' => $price,
    ]);

    $count++;
}

$pdo->commit();

fwrite(STDOUT, "{$count} 件をインポートしました。\n");
