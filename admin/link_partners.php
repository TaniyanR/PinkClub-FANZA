<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = '相互リンク管理';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $action = (string)post('action', 'create');

    if ($action === 'create') {
        $name = trim((string)post('name', ''));
        $url = trim((string)post('url', ''));
        $rssUrl = trim((string)post('rss_url', ''));

        db()->prepare('INSERT INTO mutual_links(site_name,site_url,link_url,rss_url,status,is_enabled,rss_enabled,created_at,updated_at) VALUES(:name,:url,:url,:rss,"approved",1,:rss_enabled,NOW(),NOW())')
            ->execute([
                ':name' => $name,
                ':url' => $url,
                ':rss' => $rssUrl,
                ':rss_enabled' => $rssUrl !== '' ? 1 : 0,
            ]);
        if ($rssUrl !== '') {
            db()->prepare('INSERT INTO rss_sources(name,feed_url,is_enabled,created_at,updated_at) VALUES(:name,:feed,:enabled,NOW(),NOW()) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled),updated_at=NOW()')
                ->execute([':name' => $name, ':feed' => $rssUrl, ':enabled' => 1]);
        }
        $message = '相互リンクを追加しました。';
    } elseif ($action === 'toggle_link') {
        db()->prepare('UPDATE mutual_links SET is_enabled=:enabled,updated_at=NOW() WHERE id=:id')
            ->execute([
                ':enabled' => post('is_enabled', '0') === '1' ? 1 : 0,
                ':id' => (int)post('id', 0),
            ]);
        $message = '相互リンク表示設定を更新しました。';
    } elseif ($action === 'toggle_rss') {
        $enabled = post('rss_enabled', '0') === '1' ? 1 : 0;
        db()->prepare('UPDATE mutual_links SET rss_enabled=:enabled,updated_at=NOW() WHERE id=:id')
            ->execute([
                ':enabled' => $enabled,
                ':id' => (int)post('id', 0),
            ]);
        db()->prepare('UPDATE rss_sources rs INNER JOIN mutual_links ml ON rs.feed_url = ml.rss_url SET rs.is_enabled=:enabled, rs.updated_at=NOW() WHERE ml.id=:id')
            ->execute([':enabled' => $enabled, ':id' => (int)post('id', 0)]);
        $message = 'RSS表示設定を更新しました。';
    } elseif ($action === 'save_global') {
        site_setting_set_many([
            'links.show_mutual' => post('show_mutual', '0') === '1' ? '1' : '0',
            'links.show_rss_images' => post('show_rss_images', '0') === '1' ? '1' : '0',
            'links.order' => post('order_type', 'kana') === 'created' ? 'created' : 'kana',
        ]);
        $message = 'サイド表示設定を保存しました。';
    }
}

$rows = db()->query('SELECT id,site_name,site_url,rss_url,is_enabled,rss_enabled,created_at FROM mutual_links WHERE status="approved" ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>相互リンク管理</h1>
  <?php if ($message): ?><p><?= e($message) ?></p><?php endif; ?>

  <form method="post" class="admin-form--compact">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_global">
    <label><input type="checkbox" name="show_mutual" value="1" <?= site_setting_get('links.show_mutual', '1') === '1' ? 'checked' : '' ?>> 相互リンクを表示する</label>
    <label><input type="checkbox" name="show_rss_images" value="1" <?= site_setting_get('links.show_rss_images', '1') === '1' ? 'checked' : '' ?>> 画像RSSを表示する</label>
    <label>表示順
      <label><input type="radio" name="order_type" value="kana" <?= site_setting_get('links.order', 'kana') !== 'created' ? 'checked' : '' ?>> あいうえお順</label>
      <label><input type="radio" name="order_type" value="created" <?= site_setting_get('links.order', 'kana') === 'created' ? 'checked' : '' ?>> 登録順</label>
    </label>
    <button type="submit">表示設定を保存</button>
  </form>

  <hr>

  <form method="post" class="admin-form--compact">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <label>サイト名<input name="name" required></label>
    <label>URL<input name="url" type="url" required></label>
    <label>RSS URL（任意）<input name="rss_url" type="url"></label>
    <button type="submit">追加</button>
  </form>

  <table class="admin-table">
    <tr><th>ID</th><th>サイト名</th><th>URL</th><th>RSS</th><th>リンク表示</th><th>RSS表示</th><th>登録日時</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e((string)$r['id']) ?></td>
        <td><?= e((string)$r['site_name']) ?></td>
        <td><?= e((string)$r['site_url']) ?></td>
        <td><?= e((string)$r['rss_url']) ?></td>
        <td>
          <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_link"><input type="hidden" name="id" value="<?= e((string)$r['id']) ?>"><label><input type="checkbox" name="is_enabled" value="1" <?= (int)($r['is_enabled'] ?? 0) === 1 ? 'checked' : '' ?> onchange="this.form.submit()"> 表示</label></form>
        </td>
        <td>
          <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_rss"><input type="hidden" name="id" value="<?= e((string)$r['id']) ?>"><label><input type="checkbox" name="rss_enabled" value="1" <?= (int)($r['rss_enabled'] ?? 0) === 1 ? 'checked' : '' ?> onchange="this.form.submit()"> 表示</label></form>
        </td>
        <td><?= e((string)$r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
