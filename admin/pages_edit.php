<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = '固定ページ編集';
$message = null;
$error = null;

$id = (int)get('id', 0);
if ($id <= 0) {
    app_redirect('/admin/pages.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_validate_or_fail((string)post('_csrf', ''));
        $titleText = trim((string)post('title', ''));
        $body = trim((string)post('body', ''));
        $seoTitle = trim((string)post('seo_title', ''));
        $seoDescription = trim((string)post('seo_description', ''));
        $isPublished = post('is_published', '1') === '1' ? 1 : 0;

        if ($titleText === '' || $body === '') {
            throw new RuntimeException('タイトルと本文は必須です。');
        }

        db()->prepare('UPDATE fixed_pages SET title=:title,body=:body,seo_title=:seo_title,seo_description=:seo_description,is_published=:published,updated_at=NOW() WHERE id=:id')
            ->execute([
                ':title' => $titleText,
                ':body' => $body,
                ':seo_title' => $seoTitle !== '' ? $seoTitle : null,
                ':seo_description' => $seoDescription !== '' ? $seoDescription : null,
                ':published' => $isPublished,
                ':id' => $id,
            ]);
        $message = '固定ページを更新しました。';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = db()->prepare('SELECT * FROM fixed_pages WHERE id=:id LIMIT 1');
$stmt->execute([':id' => $id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($page)) {
    app_redirect('/admin/pages.php');
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>固定ページ編集</h1>
  <?php if ($message): ?><p><?= e($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="admin-form--compact">
    <?= csrf_input() ?>
    <p>スラッグ: <strong><?= e((string)$page['slug']) ?></strong></p>
    <label>タイトル
      <input name="title" value="<?= e((string)$page['title']) ?>" required>
    </label>
    <label>本文
      <textarea name="body" rows="10" required><?= e((string)$page['body']) ?></textarea>
    </label>
    <label>SEOタイトル
      <input name="seo_title" value="<?= e((string)($page['seo_title'] ?? '')) ?>">
    </label>
    <label>SEO説明文
      <textarea name="seo_description" rows="3"><?= e((string)($page['seo_description'] ?? '')) ?></textarea>
    </label>
    <label>公開設定
      <select name="is_published"><option value="1" <?= (int)$page['is_published'] === 1 ? 'selected' : '' ?>>公開</option><option value="0" <?= (int)$page['is_published'] !== 1 ? 'selected' : '' ?>>非公開</option></select>
    </label>
    <button type="submit">更新</button>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
