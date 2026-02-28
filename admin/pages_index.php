<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = '固定ページ一覧';
$message = null;

try {
    db()->exec('CREATE TABLE IF NOT EXISTS fixed_pages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(120) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        body LONGTEXT NOT NULL,
        seo_title VARCHAR(255) NULL,
        seo_description TEXT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $defaults = [
        ['slug' => 'about', 'title' => 'サイトについて', 'body' => "このサイトについての説明ページです。\n内容は管理画面から編集できます。"],
        ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'body' => "プライバシーポリシーの初期ページです。\n内容は管理画面から編集できます。"],
        ['slug' => 'contact', 'title' => 'お問い合わせ', 'body' => "お問い合わせは下記フォームよりご連絡ください。"],
    ];

    $insert = db()->prepare('INSERT INTO fixed_pages(slug,title,body,is_published,created_at,updated_at) VALUES(:slug,:title,:body,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE slug=slug');
    foreach ($defaults as $defaultPage) {
        $insert->execute($defaultPage);
    }
} catch (Throwable $e) {
    $message = '固定ページ初期化でエラーが発生しました: ' . $e->getMessage();
}

$rows = [];
try {
    $rows = db()->query('SELECT id,slug,title,is_published,updated_at FROM fixed_pages ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    if ($message === null) {
        $message = '固定ページの取得に失敗しました: ' . $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>固定ページ一覧</h1>
  <?php if ($message !== null): ?><p><?= e($message) ?></p><?php endif; ?>
  <p>「新規」は現在保留中です。標準ページは自動作成されます。</p>
  <table class="admin-table">
    <tr><th>ID</th><th>スラッグ</th><th>タイトル</th><th>公開</th><th>更新日時</th></tr>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td><?= e((string)$row['id']) ?></td>
        <td><?= e((string)$row['slug']) ?></td>
        <td><?= e((string)$row['title']) ?></td>
        <td><?= (int)$row['is_published'] === 1 ? '公開' : '非公開' ?></td>
        <td><?= e((string)$row['updated_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
