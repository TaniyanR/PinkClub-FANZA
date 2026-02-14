<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:rss.php');
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/../../lib/site_settings.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            db()->prepare('INSERT INTO rss_sources(name, feed_url, is_enabled, last_fetched_at, created_at, updated_at) VALUES (:n,:u,:e,NULL,NOW(),NOW())')
                ->execute([':n' => trim((string)$_POST['name']), ':u' => trim((string)$_POST['feed_url']), ':e' => isset($_POST['is_enabled']) ? 1 : 0]);
            admin_flash_set('ok', 'RSSソースを追加しました。');
        } elseif ($action === 'update') {
            db()->prepare('UPDATE rss_sources SET name=:n, feed_url=:u, is_enabled=:e, updated_at=NOW() WHERE id=:id')
                ->execute([':id' => (int)$_POST['source_id'], ':n' => trim((string)$_POST['name']), ':u' => trim((string)$_POST['feed_url']), ':e' => isset($_POST['is_enabled']) ? 1 : 0]);
            admin_flash_set('ok', 'RSSソースを更新しました。');
        } elseif ($action === 'delete') {
            db()->prepare('DELETE FROM rss_sources WHERE id=:id')->execute([':id' => (int)$_POST['source_id']]);
            admin_flash_set('ok', 'RSSソースを削除しました。');
        } elseif ($action === 'fetch') {
            $ret = rss_fetch_source((int)$_POST['source_id'], 5);
            admin_flash_set('ok', 'RSS取得: ' . (string)($ret['message'] ?? '完了'));
        } elseif ($action === 'save_filters') {
            site_setting_set_many([
                'rss.ng_category_words' => trim((string)($_POST['ng_category_words'] ?? '')),
                'rss.ng_tag_words' => trim((string)($_POST['ng_tag_words'] ?? '')),
            ]);
            admin_flash_set('ok', 'RSS禁止ワード設定を保存しました。');
        }
        header('Location: ' . admin_url('rss.php'));
        exit;
    }
}

$sources = db()->query('SELECT * FROM rss_sources ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$items = db()->query('SELECT r.*, s.name AS source_name FROM rss_items r JOIN rss_sources s ON s.id=r.source_id ORDER BY r.published_at DESC, r.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$ok = admin_flash_get('ok');
$ngCategoryWords = site_setting_get('rss.ng_category_words', '');
$ngTagWords = site_setting_get('rss.ng_tag_words', '');
$pageTitle = 'RSS管理';
ob_start();
?>
<h1>RSS管理</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card"><h2>RSS禁止ワード</h2><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="save_filters"><label>禁止カテゴリーワード（改行区切り）</label><textarea name="ng_category_words" rows="4"><?php echo e($ngCategoryWords); ?></textarea><label>禁止タグワード（改行区切り）</label><textarea name="ng_tag_words" rows="4"><?php echo e($ngTagWords); ?></textarea><button>保存</button></form></div>
<div class="admin-card"><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="create"><label>ソース名</label><input name="name" required><label>RSS URL</label><input name="feed_url" required><label><input type="checkbox" name="is_enabled" checked> 有効</label><button>追加</button></form></div>
<div class="admin-card"><table class="admin-table"><thead><tr><th>ID</th><th>名</th><th>URL</th><th>状態</th><th>最終取得</th><th>件数</th><th>画像</th><th>操作</th></tr></thead><tbody>
<?php foreach ($sources as $s) : $sid=(int)$s['id']; $stats=db()->prepare('SELECT COUNT(*) AS c, SUM(CASE WHEN image_url IS NULL OR image_url="" THEN 0 ELSE 1 END) AS img FROM rss_items WHERE source_id=:id'); $stats->execute([':id'=>$sid]); $stat=$stats->fetch(PDO::FETCH_ASSOC) ?: ['c'=>0,'img'=>0]; ?><tr><td><?php echo e((string)$s['id']); ?></td><td><input form="f<?php echo e((string)$sid); ?>" name="name" value="<?php echo e((string)$s['name']); ?>"></td><td><input form="f<?php echo e((string)$sid); ?>" name="feed_url" value="<?php echo e((string)$s['feed_url']); ?>"></td><td><label><input form="f<?php echo e((string)$sid); ?>" type="checkbox" name="is_enabled" value="1" <?php echo ((int)$s['is_enabled']===1)?'checked':''; ?>>ON</label></td><td><?php echo e((string)($s['last_fetched_at'] ?? '-')); ?></td><td><?php echo e((string)$stat['c']); ?></td><td><?php echo e((string)$stat['img']); ?></td><td><form id="f<?php echo e((string)$sid); ?>" method="post" style="display:inline"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="source_id" value="<?php echo e((string)$sid); ?>"><button>更新</button></form><form method="post" style="display:inline"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="fetch"><input type="hidden" name="source_id" value="<?php echo e((string)$sid); ?>"><button>取得実行</button></form><form method="post" style="display:inline"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="source_id" value="<?php echo e((string)$sid); ?>"><button>削除</button></form></td></tr><?php endforeach; ?>
<?php if ($sources === []) : ?><tr><td colspan="8">RSSソースがありません。</td></tr><?php endif; ?>
</tbody></table></div>
<div class="admin-card"><h2>キャッシュ済み記事</h2><table class="admin-table"><thead><tr><th>日時</th><th>ソース</th><th>タイトル</th></tr></thead><tbody><?php foreach($items as $i): ?><tr><td><?php echo e((string)$i['published_at']); ?></td><td><?php echo e((string)$i['source_name']); ?></td><td><a href="<?php echo e((string)$i['url']); ?>" target="_blank" rel="noopener"><?php echo e((string)$i['title']); ?></a></td></tr><?php endforeach; ?><?php if($items===[]): ?><tr><td colspan="3">記事はありません。</td></tr><?php endif; ?></tbody></table></div>
<?php $main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
