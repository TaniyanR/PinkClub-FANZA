<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';

$count = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
echo "items テーブルのレコード数: {$count}\n";

if ($count > 0) {
    $row = db()->query('SELECT id, content_id, title, image_large, image_list, image_small FROM items ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    var_dump($row);
}
