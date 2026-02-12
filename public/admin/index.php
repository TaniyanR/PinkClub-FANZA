<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/db.php';
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'); }
$cards=[];
foreach ([['items','作品数'],['page_views','PVログ'],['pages','固定ページ'],['rss_items','RSS記事']] as [$t,$l]) { try{$c=(int)db()->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();$cards[]=['label'=>$l,'count'=>$c];}catch(Throwable $e){}}
$pageTitle='ダッシュボード';ob_start(); ?>
<h1>管理ダッシュボード</h1>
<div class="admin-grid"><?php foreach($cards as $c): ?><div class="admin-card"><strong><?php echo e($c['label']); ?></strong><p><?php echo e((string)$c['count']); ?></p></div><?php endforeach; ?></div>
<div class="admin-card"><h2>クイック導線</h2><ul><li><a href="<?php echo e(admin_url('analytics.php')); ?>">アクセス解析</a></li><li><a href="<?php echo e(admin_url('page_edit.php')); ?>">固定ページ追加</a></li><li><a href="<?php echo e(admin_url('rss.php')); ?>">RSS更新</a></li></ul></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
