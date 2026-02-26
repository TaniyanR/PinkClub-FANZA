<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = '管理ページ一覧';
$pages = [
    ['url' => 'index.php', 'label' => 'ダッシュボード'],
    ['url' => 'settings.php', 'label' => '設定'],
    ['url' => 'sync_floors.php', 'label' => 'フロア同期'],
    ['url' => 'sync_master.php', 'label' => 'マスタ同期'],
    ['url' => 'sync_items.php', 'label' => '商品同期'],
    ['url' => 'sync_logs.php', 'label' => '同期ログ'],
    ['url' => 'sitemap.php', 'label' => '管理ページ一覧'],
    ['url' => 'logout.php', 'label' => 'ログアウト'],
];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>管理ページ一覧</h1>
  <p class="admin-form-note">管理画面で利用できる主要ページです。</p>
  <ul class="admin-list">
    <?php foreach ($pages as $page): ?>
      <li><a href="<?= e(admin_url($page['url'])) ?>"><?= e($page['label']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
