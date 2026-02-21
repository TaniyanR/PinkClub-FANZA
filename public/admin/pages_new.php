<?php

declare(strict_types=1);

require_once __DIR__ . '/_fixed_pages.php';

$errors = [];
$form = [
    'title' => '',
    'slug' => '',
    'body' => '',
    'status' => 'draft',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $errors[] = 'CSRFトークンが無効です。';
    }

    $form = admin_fixed_page_form_from_post();
    $errors = array_merge($errors, admin_fixed_page_validate($form));

    if ($errors === []) {
        try {
            admin_fixed_page_create($form);
            admin_flash_set('pages_ok', '固定ページを作成しました。');
            header('Location: ' . admin_url('pages.php'));
            exit;
        } catch (Throwable $exception) {
            error_log('[admin/pages] create failed: ' . $exception->getMessage());
            $errors[] = '固定ページの作成に失敗しました。';
        }
    }
}

$pageTitle = '固定ページ新規作成';
ob_start();
?>
<h1>固定ページ新規作成</h1>

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

        <button type="submit">作成</button>
        <a href="<?php echo e(admin_url('pages.php')); ?>">一覧に戻る</a>
    </form>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
