<?php

declare(strict_types=1);

require_once __DIR__ . '/_fixed_pages.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    admin_flash_set('pages_error', '削除はPOSTで実行してください。');
    header('Location: ' . admin_url('pages.php'));
    exit;
}

if (!admin_post_csrf_valid()) {
    admin_flash_set('pages_error', 'CSRFトークンが無効です。');
    header('Location: ' . admin_url('pages.php'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    admin_flash_set('pages_error', '削除対象が不正です。');
    header('Location: ' . admin_url('pages.php'));
    exit;
}

try {
    if (admin_fixed_page_delete($id)) {
        admin_flash_set('pages_ok', '固定ページを削除しました。');
    } else {
        admin_flash_set('pages_error', '固定ページが見つかりませんでした。');
    }
} catch (Throwable $exception) {
    error_log('[admin/pages] delete failed: ' . $exception->getMessage());
    admin_flash_set('pages_error', '固定ページの削除に失敗しました。');
}

header('Location: ' . admin_url('pages.php'));
exit;
