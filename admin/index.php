<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';

$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

/**
 * 想定外URLの吸収
 * - /admin/index.php/public... に来たら /admin/index.php に 301 で戻す
 * - /admin/index.php/xxxx のように index.php の後ろに余計なパスが付いたら 404
 */
if ($requestPath !== '' && preg_match('#/admin/index\.php/public(?:/.*)?$#', $requestPath) === 1) {
    header('Location: ' . app_url('/admin/index.php'), true, 301);
    exit;
}

if ($requestPath !== '' && preg_match('#/admin/index\.php/(.+)$#', $requestPath) === 1) {
    http_response_code(404);
    exit('Not Found');
}

auth_require_admin();

$title = 'ダッシュボード';
$tables = ['items', 'actresses', 'genres', 'makers', 'series_master', 'authors', 'dmm_floors'];
$counts = [];
foreach ($tables as $t) {
    $counts[$t] = (int) db()->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
}
$labels = [
    'items' => '商品',
    'actresses' => '女優',
    'genres' => 'ジャンル',
    'makers' => 'メーカー',
    'series_master' => 'シリーズ',
    'authors' => '作者',
    'dmm_floors' => 'フロア',
];
$logs = db()->query('SELECT * FROM sync_logs ORDER BY id DESC LIMIT 20')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>ダッシュボード</h1>
  <p class="admin-form-note">同期対象の件数と最新の同期状態を確認できます。</p>
</section>

<section class="admin-card">
  <h2>同期対象データ件数</h2>
  <table class="admin-table">
    <tr><th>項目</th><th>件数</th></tr>
    <?php foreach ($counts as $key => $count): ?>
      <tr><td><?= e($labels[$key] ?? $key) ?></td><td><?= e((string)$count) ?></td></tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="admin-card">
  <h2>API一覧</h2>
  <ul>
    <li><a href="<?= e(admin_url('api_items.php')) ?>">商品情報API設定</a></li>
    <li><a href="<?= e(admin_url('api_genres.php')) ?>">ジャンルAPI設定</a></li>
    <li><a href="<?= e(admin_url('api_actresses.php')) ?>">女優API設定</a></li>
    <li><a href="<?= e(admin_url('api_series.php')) ?>">シリーズAPI設定</a></li>
  </ul>
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
