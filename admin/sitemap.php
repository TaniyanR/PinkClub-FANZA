<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
require_once __DIR__ . '/../lib/admin_page_discovery.php';

$title = '管理ページ一覧';
$pages = admin_discover_pages();

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>管理ページ一覧（サイトマップ）</h1>
  <p class="admin-form-note">検出した管理ページを一覧表示します。未整備は導線調整が必要な候補です。</p>
  <table class="admin-table">
    <tr><th>パス</th><th>ラベル</th><th>状態</th><th>認証</th><th>存在確認</th></tr>
    <?php foreach ($pages as $page): ?>
      <tr>
        <td><a href="<?= e(url((string)$page['path'])) ?>"><?= e((string)$page['path']) ?></a></td>
        <td><?= e((string)$page['label']) ?></td>
        <td><?= $page['broken'] ? '未整備' : '稼働候補' ?></td>
        <td><?= e((string)$page['auth']) ?></td>
        <td><?= $page['exists'] ? 'OK' : 'NG' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
