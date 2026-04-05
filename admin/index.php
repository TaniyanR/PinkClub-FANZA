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
$failedLogs = db()->query('SELECT * FROM sync_logs WHERE is_success = 0 ORDER BY id DESC LIMIT 10')->fetchAll();
$todayViews = 0;
try {
    $todayViews = (int) db()->query('SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at) = CURDATE()')->fetchColumn();
} catch (Throwable $e) {
    $todayViews = 0;
}

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
  <h2>アクセス情報（簡易）</h2>
  <table class="admin-table">
    <tr><th>項目</th><th>値</th></tr>
    <tr><td>本日のPV</td><td><?= e((string)$todayViews) ?></td></tr>
  </table>
</section>

<section class="admin-card">
  <h2>不具合情報（直近失敗ログ）</h2>
  <table class="admin-table">
    <tr><th>ID</th><th>種別</th><th>メッセージ</th><th>時刻</th></tr>
    <?php foreach ($failedLogs as $log): ?>
      <tr>
        <td><?= e($log['id']) ?></td>
        <td><?= e($log['sync_type']) ?></td>
        <td><?= e($log['message']) ?></td>
        <td><?= e($log['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>