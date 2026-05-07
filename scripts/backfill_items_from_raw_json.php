<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';

$stmt = db()->query('SELECT id, title, image_large, image_small, image_list, raw_json FROM items');
$rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

$updated = 0;
$updateStmt = db()->prepare('UPDATE items SET title = :title, image_large = :image_large, image_small = :image_small, image_list = :image_list, updated_at = NOW() WHERE id = :id');

foreach ($rows as $row) {
    $raw = json_decode((string)($row['raw_json'] ?? ''), true);
    if (!is_array($raw)) {
        continue;
    }

    $title = trim((string)($row['title'] ?? ''));
    if ($title === '') {
        $title = trim((string)($raw['title'] ?? ''));
    }

    $imageLarge = trim((string)($row['image_large'] ?? ''));
    $imageSmall = trim((string)($row['image_small'] ?? ''));
    $imageList  = trim((string)($row['image_list'] ?? ''));

    $rawImage = $raw['imageURL'] ?? [];
    if ($imageLarge === '' && is_array($rawImage)) {
        $imageLarge = trim((string)($rawImage['large'] ?? ''));
    }
    if ($imageSmall === '' && is_array($rawImage)) {
        $imageSmall = trim((string)($rawImage['small'] ?? ''));
    }
    if ($imageList === '' && is_array($rawImage)) {
        $imageList = trim((string)($rawImage['list'] ?? ''));
    }

    $origTitle = trim((string)($row['title'] ?? ''));
    $origLarge = trim((string)($row['image_large'] ?? ''));
    $origSmall = trim((string)($row['image_small'] ?? ''));
    $origList  = trim((string)($row['image_list'] ?? ''));

    if ($title === $origTitle && $imageLarge === $origLarge && $imageSmall === $origSmall && $imageList === $origList) {
        continue;
    }

    $updateStmt->execute([
        ':id' => (int)$row['id'],
        ':title' => $title,
        ':image_large' => $imageLarge !== '' ? $imageLarge : null,
        ':image_small' => $imageSmall !== '' ? $imageSmall : null,
        ':image_list' => $imageList !== '' ? $imageList : null,
    ]);
    $updated++;
}

echo "更新件数: {$updated}\n";
