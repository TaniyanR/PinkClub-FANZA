<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'pending');
        $displayPos = (string)($_POST['display_position'] ?? 'sidebar');
        $rssEnabled = isset($_POST['rss_enabled']) ? 1 : 0;
        if ($id > 0 && in_array($status, ['pending', 'approved', 'rejected', 'hold'], true)) {
            db()->prepare('UPDATE mutual_links SET status=:st, display_position=:pos, rss_enabled=:rss, updated_at=NOW() WHERE id=:id')
                ->execute([':st' => $status, ':pos' => $displayPos, ':rss' => $rssEnabled, ':id' => $id]);
            admin_flash_set('ok', '相互リンクを更新しました。');
            header('Location: ' . admin_url('links.php'));
            exit;
        }
    }
}

$rows = db()->query('SELECT * FROM mutual_links ORDER BY created_at DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$ok = admin_flash_get('ok');
$pageTitle = '相互リンク管理';
ob_start();
?>
<h1>相互リンク管理</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
    <p>外部申請フォームは <a href="<?php echo e(base_url() . '/link_apply.php'); ?>" target="_blank" rel="noopener">こちら</a>。</p>
</div>
<div class="admin-card">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>サイト名</th><th>URL</th><th>ステータス</th><th>表示</th><th>RSS</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) : ?>
            <tr>
                <td><?php echo e((string)$r['id']); ?></td>
                <td><?php echo e((string)$r['site_name']); ?></td>
                <td><a href="<?php echo e((string)$r['site_url']); ?>" target="_blank" rel="noopener"><?php echo e((string)$r['site_url']); ?></a></td>
                <td>
                    <form method="post">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>">
                        <select name="status">
                            <?php foreach (['pending' => '保留', 'approved' => '承認', 'hold' => '保留(任意)', 'rejected' => '却下'] as $v => $lbl) : ?>
                                <option value="<?php echo e($v); ?>" <?php echo ((string)$r['status'] === $v) ? 'selected' : ''; ?>><?php echo e($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                </td>
                <td>
                    <select name="display_position">
                        <?php foreach (['sidebar', 'content_top', 'content_bottom', 'sp_bottom'] as $v) : ?>
                            <option value="<?php echo e($v); ?>" <?php echo ((string)($r['display_position'] ?? 'sidebar') === $v) ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="checkbox" name="rss_enabled" value="1" <?php echo ((int)($r['rss_enabled'] ?? 0) === 1) ? 'checked' : ''; ?>></td>
                <td><button type="submit">更新</button></td>
                    </form>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []) : ?><tr><td colspan="7">申請はまだありません。</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
