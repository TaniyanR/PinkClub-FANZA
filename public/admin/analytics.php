<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';require_once __DIR__ . '/../../lib/db.php';
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
$days=(int)($_GET['days']??7); if(!in_array($days,[1,2,7,30],true)){$days=7;}
$from=date('Y-m-d 00:00:00',strtotime('-'.($days-1).' days'));
$pdo=db();
$pv=(int)$pdo->prepare('SELECT COUNT(*) FROM page_views WHERE viewed_at>=:f');$stmt=$pdo->prepare('SELECT COUNT(*) FROM page_views WHERE viewed_at>=:f');$stmt->execute([':f'=>$from]);$pv=(int)$stmt->fetchColumn();
$st=$pdo->prepare('SELECT COUNT(*) FROM (SELECT DATE(viewed_at),ip_hash,ua_hash FROM page_views WHERE viewed_at>=:f GROUP BY DATE(viewed_at),ip_hash,ua_hash) t');$st->execute([':f'=>$from]);$uu=(int)$st->fetchColumn();
$top=$pdo->prepare('SELECT i.title,p.item_cid,COUNT(*) c FROM page_views p LEFT JOIN items i ON i.content_id=p.item_cid WHERE p.viewed_at>=:f AND p.item_cid IS NOT NULL GROUP BY p.item_cid,i.title ORDER BY c DESC LIMIT 10');$top->execute([':f'=>$from]);$topRows=$top->fetchAll(PDO::FETCH_ASSOC);
$ref=$pdo->prepare('SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(referrer,\'/\',3),\'/\',-1) domain,COUNT(*) c FROM page_views WHERE viewed_at>=:f AND referrer IS NOT NULL AND referrer<>\'\' GROUP BY domain ORDER BY c DESC LIMIT 10');$ref->execute([':f'=>$from]);$refRows=$ref->fetchAll(PDO::FETCH_ASSOC);
$pageTitle='PV/UU・アクセス解析';ob_start();?>
<h1>アクセス解析</h1><div class="admin-card"><a href="?days=1">今日</a> | <a href="?days=2">昨日+今日</a> | <a href="?days=7">7日</a> | <a href="?days=30">30日</a><p>PV: <?php echo e((string)$pv); ?> / UU: <?php echo e((string)$uu); ?></p></div>
<div class="admin-card"><h2>人気作品TOP10</h2><table><tr><th>作品</th><th>PV</th></tr><?php foreach($topRows as $r): ?><tr><td><?php echo e((string)($r['title']?:$r['item_cid'])); ?></td><td><?php echo e((string)$r['c']); ?></td></tr><?php endforeach; ?></table></div>
<div class="admin-card"><h2>参照元ドメインTOP</h2><table><tr><th>ドメイン</th><th>PV</th></tr><?php foreach($refRows as $r): ?><tr><td><?php echo e((string)$r['domain']); ?></td><td><?php echo e((string)$r['c']); ?></td></tr><?php endforeach; ?></table></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
