<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    admin_flash_set('import_items_error', '削除はPOSTで実行してください。');
    header('Location: ' . admin_url('import_items.php'));
    exit;
}

if (!admin_post_csrf_valid()) {
    admin_flash_set('import_items_error', 'CSRFトークンが無効です。');
    header('Location: ' . admin_url('import_items.php'));
    exit;
}

$idParam = $_POST['id'] ?? null;
if (!is_string($idParam) || $idParam === '' || ctype_digit($idParam) === false) {
    admin_flash_set('import_items_error', '削除対象のIDが不正です。');
    header('Location: ' . admin_url('import_items.php'));
    exit;
}

$itemId = (int)$idParam;
if ($itemId <= 0) {
    admin_flash_set('import_items_error', '削除対象のIDが不正です。');
    header('Location: ' . admin_url('import_items.php'));
    exit;
}

try {
    $stmt = db()->prepare('DELETE FROM items WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $itemId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        admin_flash_set('import_items_ok', '削除しました。');
    } else {
        admin_flash_set('import_items_error', '指定されたデータが見つかりませんでした。');
    }
} catch (Throwable $e) {
    error_log('item_delete failed: ' . $e->getMessage());
    admin_flash_set('import_items_error', '処理に失敗しました。');
}

header('Location: ' . admin_url('import_items.php'));
exit;

