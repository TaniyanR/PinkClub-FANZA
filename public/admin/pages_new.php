<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $slug = trim((string)($_POST['slug'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $body = (string)($_POST['body'] ?? '');
        $published = isset($_POST['is_published']) ? 1 : 0;

        if ($slug === '' || $title === '') {
            $error = 'slug とタイトルは必須です。';
        } else {
            $stmt = db()->prepare('INSERT INTO fixed_pages(slug,title,body,is_published,created_at,updated_at) VALUES (:slug,:title,:body,:pub,NOW(),NOW())');
            $stmt->execute([':slug' => $slug, ':title' => $title, ':body' => $body, ':pub' => $published]);
            $id = (int)db()->lastInsertId();
            admin_flash_set('ok', '固定ページを作成しました。');
            header('Location: ' . admin_url('pages.php?edit=' . $id));
            exit;
        }
    }
}

$pageTitle = '固定ページ新規作成';
ob_start();
?>
<h1>固定ページ新規作成</h1>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <label>slug</label>
        <input type="text" name="slug" required>
        <label>タイトル</label>
        <input type="text" name="title" required>
        <label>本文</label>
        <textarea name="body" rows="12"></textarea>
        <label><input type="checkbox" name="is_published" value="1" checked> 公開</label>
        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
