<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';

$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($requestPath !== '' && preg_match('#/admin/index\.php/(.+)$#', $requestPath) === 1) {
    http_response_code(404);
    exit('Not Found');
}

auth_require_admin();

$title = 'Dashboard';
$tables = ['items', 'actresses', 'genres', 'makers', 'series_master', 'authors', 'dmm_floors'];
$counts = [];
foreach ($tables as $t) {
    $counts[$t] = (int) db()->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
}
$logs = db()->query('SELECT * FROM sync_logs ORDER BY id DESC LIMIT 20')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>Dashboard</h1>
  <p class="admin-form-note">同期対象の件数と最新の同期状態を確認できます。</p>
</section>

<section class="admin-card">
  <h2>件数サマリ</h2>
  <div class="admin-status-grid">
    <?php foreach ($counts as $name => $count): ?>
      <article class="admin-card admin-status-card">
        <strong><?= e($name) ?></strong>
        <p><?= e((string) $count) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <h2>最近の同期ログ</h2>
  <table class="admin-table">
    <tr><th>ID</th><th>種別</th><th>成否</th><th>件数</th><th>メッセージ</th><th>時刻</th></tr>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td><?= e($log['id']) ?></td>
        <td><?= e($log['sync_type']) ?></td>
        <td><?= $log['is_success'] ? 'OK' : 'NG' ?></td>
        <td><?= e($log['synced_count']) ?></td>
        <td><?= e($log['message']) ?></td>
        <td><?= e($log['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
