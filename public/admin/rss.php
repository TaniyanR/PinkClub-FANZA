<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/app_features.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            db()->prepare('INSERT INTO rss_sources(name, feed_url, is_enabled, last_fetched_at) VALUES (:n,:u,:e,NULL)')
                ->execute([':n' => trim((string)$_POST['name']), ':u' => trim((string)$_POST['feed_url']), ':e' => isset($_POST['is_enabled']) ? 1 : 0]);
            admin_flash_set('ok', 'RSSソースを追加しました。');
        } elseif ($action === 'fetch') {
            $ret = rss_fetch_source((int)$_POST['source_id'], 5);
            admin_flash_set('ok', 'RSS取得: ' . (string)($ret['message'] ?? '完了'));
        }
        header('Location: ' . admin_url('rss.php'));
        exit;
    }
}

$sources = db()->query('SELECT * FROM rss_sources ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$items = db()->query('SELECT r.*, s.name AS source_name FROM rss_items r JOIN rss_sources s ON s.id=r.source_id ORDER BY r.published_at DESC, r.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$ok = admin_flash_get('ok');
$pageTitle = 'RSS管理';
ob_start();
?>
<h1>RSS管理</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card"><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="create"><label>ソース名</label><input name="name" required><label>RSS URL</label><input name="feed_url" required><label><input type="checkbox" name="is_enabled" checked> 有効</label><button>追加</button></form></div>
<div class="admin-card"><table class="admin-table"><thead><tr><th>ID</th><th>名</th><th>URL</th><th>最終取得</th><th>取得</th></tr></thead><tbody>
<?php foreach ($sources as $s) : ?><tr><td><?php echo e((string)$s['id']); ?></td><td><?php echo e((string)$s['name']); ?></td><td><?php echo e((string)$s['feed_url']); ?></td><td><?php echo e((string)($s['last_fetched_at'] ?? '-')); ?></td><td><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="fetch"><input type="hidden" name="source_id" value="<?php echo e((string)$s['id']); ?>"><button>今すぐ取得</button></form></td></tr><?php endforeach; ?>
<?php if ($sources === []) : ?><tr><td colspan="5">RSSソースがありません。</td></tr><?php endif; ?>
</tbody></table></div>
<div class="admin-card"><h2>キャッシュ済み記事</h2><table class="admin-table"><thead><tr><th>日時</th><th>ソース</th><th>タイトル</th></tr></thead><tbody><?php foreach($items as $i): ?><tr><td><?php echo e((string)$i['published_at']); ?></td><td><?php echo e((string)$i['source_name']); ?></td><td><a href="<?php echo e((string)$i['url']); ?>" target="_blank" rel="noopener"><?php echo e((string)$i['title']); ?></a></td></tr><?php endforeach; ?><?php if($items===[]): ?><tr><td colspan="3">記事はありません。</td></tr><?php endif; ?></tbody></table></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
