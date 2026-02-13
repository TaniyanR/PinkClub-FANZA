<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$period = (string)($_GET['period'] ?? 'day');
$periodMap = ['day' => 1, 'week' => 7, 'month' => 30];
if (!isset($periodMap[$period])) {
    $period = 'day';
}
$days = $periodMap[$period];

$pvStmt = db()->prepare('SELECT COUNT(*) FROM page_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL :d DAY)');
$pvStmt->bindValue(':d', $days, PDO::PARAM_INT);
$pvStmt->execute();
$pv = (int)($pvStmt->fetchColumn() ?: 0);

$uuStmt = db()->prepare('SELECT COUNT(*) FROM (SELECT ip_hash, ua_hash FROM page_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL :d DAY) GROUP BY ip_hash, ua_hash) t');
$uuStmt->bindValue(':d', $days, PDO::PARAM_INT);
$uuStmt->execute();
$uu = (int)($uuStmt->fetchColumn() ?: 0);

$eventStmt = db()->prepare('SELECT event_type, COUNT(*) c FROM access_events WHERE event_at >= DATE_SUB(NOW(), INTERVAL :d DAY) GROUP BY event_type ORDER BY c DESC');
$eventStmt->bindValue(':d', $days, PDO::PARAM_INT);
$eventStmt->execute();
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

$refStmt = db()->prepare('SELECT COALESCE(NULLIF(SUBSTRING_INDEX(referrer,"/",3),""),"direct") ref, COUNT(*) c FROM page_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL :d DAY) GROUP BY ref ORDER BY c DESC LIMIT 20');
$refStmt->bindValue(':d', $days, PDO::PARAM_INT);
$refStmt->execute();
$refs = $refStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'アクセス解析';
ob_start();
?>
<h1>アクセス解析</h1>
<div class="admin-card">
    <a href="<?php echo e(admin_url('analytics.php?period=day')); ?>">日</a> |
    <a href="<?php echo e(admin_url('analytics.php?period=week')); ?>">週</a> |
    <a href="<?php echo e(admin_url('analytics.php?period=month')); ?>">月</a>
</div>
<div class="admin-status-grid">
    <div class="admin-card admin-status-card"><strong>PV</strong><p><?php echo e((string)$pv); ?></p></div>
    <div class="admin-card admin-status-card"><strong>UU</strong><p><?php echo e((string)$uu); ?></p></div>
</div>
<div class="admin-card"><h2>IN / OUT / その他</h2><table class="admin-table"><thead><tr><th>種別</th><th>件数</th></tr></thead><tbody><?php foreach($events as $r): ?><tr><td><?php echo e((string)$r['event_type']); ?></td><td><?php echo e((string)$r['c']); ?></td></tr><?php endforeach; ?><?php if($events===[]): ?><tr><td colspan="2">この期間のイベントは0件です。</td></tr><?php endif; ?></tbody></table></div>
<div class="admin-card"><h2>リファラ</h2><table class="admin-table"><thead><tr><th>参照元</th><th>件数</th></tr></thead><tbody><?php foreach($refs as $r): ?><tr><td><?php echo e((string)$r['ref']); ?></td><td><?php echo e((string)$r['c']); ?></td></tr><?php endforeach; ?><?php if($refs===[]): ?><tr><td colspan="2">この期間のリファラは0件です。</td></tr><?php endif; ?></tbody></table></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
