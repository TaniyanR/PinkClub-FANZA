<?php

declare(strict_types=1);

require_once __DIR__ . '/_fixed_pages.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    admin_flash_set('pages_error', '編集対象が不正です。');
    header('Location: ' . admin_url('pages.php'));
    exit;
}

$errors = [];
$record = null;

try {
    $record = admin_fixed_page_find($id);
} catch (Throwable $exception) {
    error_log('[admin/pages] edit fetch failed: ' . $exception->getMessage());
}

if ($record === null) {
    admin_flash_set('pages_error', '指定された固定ページが見つかりません。');
    header('Location: ' . admin_url('pages.php'));
    exit;
}

$form = [
    'title' => $record['title'],
    'slug' => $record['slug'],
    'body' => $record['body'],
    'status' => $record['status'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $errors[] = 'CSRFトークンが無効です。';
    }

    $form = admin_fixed_page_form_from_post();
    $errors = array_merge($errors, admin_fixed_page_validate($form, $id));

    if ($errors === []) {
        try {
            admin_fixed_page_update($id, $form);
            admin_flash_set('pages_ok', '固定ページを更新しました。');
            header('Location: ' . admin_url('pages.php'));
            exit;
        } catch (Throwable $exception) {
            error_log('[admin/pages] update failed: ' . $exception->getMessage());
            $errors[] = '固定ページの更新に失敗しました。';
        }
    }
}

$pageTitle = '固定ページ編集';
ob_start();
?>
<h1>固定ページ編集</h1>

<?php if ($errors !== []) : ?>
    <div class="admin-card">
        <?php foreach ($errors as $error) : ?>
            <p><?php echo e((string)$error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="id" value="<?php echo e((string)$id); ?>">

        <label>タイトル</label>
        <input name="title" required value="<?php echo e($form['title']); ?>">

        <label>スラッグ</label>
        <input name="slug" required value="<?php echo e($form['slug']); ?>" placeholder="about-us">

        <label>公開状態</label>
        <select name="status">
            <option value="draft" <?php echo $form['status'] === 'draft' ? 'selected' : ''; ?>>draft</option>
            <option value="published" <?php echo $form['status'] === 'published' ? 'selected' : ''; ?>>published</option>
        </select>

        <label>本文</label>
        <textarea name="body" rows="12"><?php echo e($form['body']); ?></textarea>

        <button type="submit">更新</button>
        <a href="<?php echo e(admin_url('pages.php')); ?>">一覧に戻る</a>
    </form>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
