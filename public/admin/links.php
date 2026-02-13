<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $action = (string)($_POST['action'] ?? 'update_link');
        if ($action === 'save_sort_mode') {
            $mode = (string)($_POST['links_sort_mode'] ?? 'manual');
            if (!in_array($mode, ['manual', 'approved_desc', 'in_desc', 'random'], true)) {
                $mode = 'manual';
            }
            $scope = (string)($_POST['links_display_scope'] ?? 'both');
            if (!in_array($scope, ['home', 'item', 'both'], true)) {
                $scope = 'both';
            }
            site_setting_set_many([
                'links.sort_mode' => $mode,
                'links.display_scope' => $scope,
            ]);
            admin_flash_set('ok', '表示設定を保存しました。');
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'pending');
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
            if ($id > 0 && in_array($status, ['pending', 'approved', 'rejected', 'hold'], true)) {
                db()->prepare('UPDATE mutual_links SET status=:status, is_enabled=:is_enabled, display_order=:display_order, approved_at=CASE WHEN :status="approved" AND approved_at IS NULL THEN NOW() ELSE approved_at END, updated_at=NOW() WHERE id=:id')
                    ->execute([':status' => $status, ':is_enabled' => $isEnabled, ':display_order' => $displayOrder, ':id' => $id]);

                if ($status === 'approved') {
                    $link = db()->prepare('SELECT site_name,rss_url FROM mutual_links WHERE id=:id LIMIT 1');
                    $link->execute([':id' => $id]);
                    $row = $link->fetch(PDO::FETCH_ASSOC);
                    if (is_array($row) && (string)($row['rss_url'] ?? '') !== '') {
                        db()->prepare('INSERT INTO rss_sources(name,feed_url,is_enabled,last_fetched_at,created_at,updated_at) VALUES(:name,:url,1,NULL,NOW(),NOW()) ON DUPLICATE KEY UPDATE updated_at=NOW()')
                            ->execute([':name' => (string)$row['site_name'], ':url' => (string)$row['rss_url']]);
                    }
                }
                admin_flash_set('ok', '相互リンクを更新しました。');
            }
        }
        header('Location: ' . admin_url('links.php'));
        exit;
    }
}

$rows = db()->query('SELECT * FROM mutual_links ORDER BY created_at DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$ok = admin_flash_get('ok');
$sortMode = site_setting_get('links.sort_mode', 'manual');
$displayScope = site_setting_get('links.display_scope', 'both');
$pageTitle = '相互リンク管理';
ob_start();
?>
<h1>相互リンク管理</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="save_sort_mode">
        <label>フロント表示順</label>
        <select name="links_sort_mode">
            <option value="manual" <?php echo $sortMode === 'manual' ? 'selected' : ''; ?>>手動（display_order）</option>
            <option value="approved_desc" <?php echo $sortMode === 'approved_desc' ? 'selected' : ''; ?>>承認日（新しい順）</option>
            <option value="in_desc" <?php echo $sortMode === 'in_desc' ? 'selected' : ''; ?>>IN多い順</option>
            <option value="random" <?php echo $sortMode === 'random' ? 'selected' : ''; ?>>ランダム</option>
        </select>
        <label>表示範囲</label>
        <select name="links_display_scope">
            <option value="home" <?php echo $displayScope === 'home' ? 'selected' : ''; ?>>トップのみ</option>
            <option value="item" <?php echo $displayScope === 'item' ? 'selected' : ''; ?>>個別ページのみ</option>
            <option value="both" <?php echo $displayScope === 'both' ? 'selected' : ''; ?>>トップ + 個別ページ</option>
        </select>
        <button type="submit">保存</button>
    </form>
    <p>外部申請フォームは <a href="<?php echo e(base_url() . '/link_apply.php'); ?>" target="_blank" rel="noopener">こちら</a>。</p>
</div>
<div class="admin-card"><table class="admin-table"><thead><tr><th>ID</th><th>サイト名</th><th>URL</th><th>申請情報</th><th>表示順</th><th>有効</th><th>ステータス</th><th>操作</th></tr></thead><tbody>
<?php foreach ($rows as $r) : ?>
<tr><td><?php echo e((string)$r['id']); ?></td><td><?php echo e((string)$r['site_name']); ?></td><td><a href="<?php echo e((string)$r['site_url']); ?>" target="_blank" rel="noopener"><?php echo e((string)$r['site_url']); ?></a></td><td><small><?php echo e((string)($r['apply_type'] ?? '-')); ?><br><?php echo e((string)($r['contact_email'] ?? '-')); ?></small></td><td>
<form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>"><input type="number" name="display_order" value="<?php echo e((string)($r['display_order'] ?? 0)); ?>" style="width:90px"></td><td><label><input type="checkbox" name="is_enabled" value="1" <?php echo ((int)($r['is_enabled'] ?? 0) === 1) ? 'checked' : ''; ?>>ON</label></td><td><select name="status"><?php foreach (['pending' => '保留', 'approved' => '承認', 'hold' => '保留', 'rejected' => '却下'] as $v => $lbl) : ?><option value="<?php echo e($v); ?>" <?php echo ((string)$r['status'] === $v) ? 'selected' : ''; ?>><?php echo e($lbl); ?></option><?php endforeach; ?></select></td><td><button type="submit">更新</button></form></td></tr>
<?php endforeach; ?>
<?php if ($rows === []) : ?><tr><td colspan="8">申請はまだありません。</td></tr><?php endif; ?>
</tbody></table></div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
