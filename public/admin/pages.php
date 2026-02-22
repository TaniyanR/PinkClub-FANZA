<?php

declare(strict_types=1);

require_once __DIR__ . '/_fixed_pages.php';

$flashOk = admin_flash_get('pages_ok');
$flashErr = admin_flash_get('pages_error');
$rows = [];

try {
    $rows = db()->query('SELECT id, title, slug, is_published, updated_at FROM fixed_pages ORDER BY updated_at DESC, id DESC')
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('[admin/pages] list fetch failed: ' . $exception->getMessage());
    $flashErr = 'データ取得に失敗しました。';
}

$pageTitle = '固定ページ一覧';
ob_start();
?>
<h1>固定ページ一覧</h1>
<?php if ($flashOk !== '') : ?>
    <div class="admin-card"><p><?php echo e($flashOk); ?></p></div>
<?php endif; ?>
<?php if ($flashErr !== '') : ?>
    <div class="admin-card"><p><?php echo e($flashErr); ?></p></div>
<?php endif; ?>

<div class="admin-card">
    <p><a href="<?php echo e(admin_url('pages_new.php')); ?>">固定ページを新規作成</a></p>
    <table class="admin-table">
        <thead>
        <tr><th>ID</th><th>タイトル</th><th>スラッグ</th><th>状態</th><th>更新日時</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row) : ?>
            <tr>
                <td><?php echo e((string)($row['id'] ?? '')); ?></td>
                <td><?php echo e((string)($row['title'] ?? '')); ?></td>
                <td><?php echo e((string)($row['slug'] ?? '')); ?></td>
                <td><?php echo ((int)($row['is_published'] ?? 0) === 1) ? '公開' : '非公開'; ?></td>
                <td><?php echo e((string)($row['updated_at'] ?? '')); ?></td>
                <td>
                    <a href="<?php echo e(admin_url('page_edit.php?id=' . (string)$row['id'])); ?>">編集</a>
                    <form method="post" action="<?php echo e(admin_url('page_delete.php')); ?>" style="display:inline" onsubmit="return confirm('この固定ページを削除しますか？');">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo e((string)$row['id']); ?>">
                        <button type="submit">削除</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []) : ?>
            <tr><td colspan="6">データなし</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
