<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$pv = (int)(db()->query('SELECT COUNT(*) FROM page_views')->fetchColumn() ?: 0);
$uu = (int)(db()->query('SELECT COUNT(*) FROM (SELECT ip_hash, ua_hash FROM page_views GROUP BY ip_hash, ua_hash) t')->fetchColumn() ?: 0);
$byDay = db()->query('SELECT DATE(viewed_at) d, COUNT(*) pv, COUNT(DISTINCT CONCAT(ip_hash,":",ua_hash)) uu FROM page_views GROUP BY DATE(viewed_at) ORDER BY d DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
$refs = db()->query('SELECT COALESCE(NULLIF(SUBSTRING_INDEX(referrer,"/",3),""),"direct") ref, COUNT(*) c FROM page_views GROUP BY ref ORDER BY c DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
$inout = db()->query("SELECT event_type, COUNT(*) c FROM access_events GROUP BY event_type ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'アクセス解析';
ob_start();
?>
<h1>アクセス解析</h1>
<div class="admin-status-grid">
<div class="admin-card admin-status-card"><strong>PV</strong><p><?php echo e((string)$pv); ?></p></div>
<div class="admin-card admin-status-card"><strong>UU</strong><p><?php echo e((string)$uu); ?></p></div>
</div>
<div class="admin-card"><h2>日次</h2><table class="admin-table"><thead><tr><th>日付</th><th>PV</th><th>UU</th></tr></thead><tbody><?php foreach($byDay as $r): ?><tr><td><?php echo e((string)$r['d']); ?></td><td><?php echo e((string)$r['pv']); ?></td><td><?php echo e((string)$r['uu']); ?></td></tr><?php endforeach; ?><?php if($byDay===[]): ?><tr><td colspan="3">データなし</td></tr><?php endif; ?></tbody></table></div>
<div class="admin-card"><h2>リファラ</h2><table class="admin-table"><thead><tr><th>参照元</th><th>件数</th></tr></thead><tbody><?php foreach($refs as $r): ?><tr><td><?php echo e((string)$r['ref']); ?></td><td><?php echo e((string)$r['c']); ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="admin-card"><h2>IN/OUTイベント</h2><table class="admin-table"><thead><tr><th>種別</th><th>件数</th></tr></thead><tbody><?php foreach($inout as $r): ?><tr><td><?php echo e((string)$r['event_type']); ?></td><td><?php echo e((string)$r['c']); ?></td></tr><?php endforeach; ?><?php if($inout===[]): ?><tr><td colspan="2">データなし</td></tr><?php endif; ?></tbody></table></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
