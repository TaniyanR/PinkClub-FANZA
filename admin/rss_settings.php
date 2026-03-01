<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
analytics_ensure_tables();
$title = 'RSS管理';
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    if ((string)post('action', '') === 'create') {
        db()->prepare('INSERT INTO partner_rss(partner_site_id,feed_url,is_enabled,created_at,updated_at) VALUES(:sid,:url,:enabled,NOW(),NOW())')->execute([
            ':sid' => (int)post('partner_site_id', 0),
            ':url' => trim((string)post('feed_url', '')),
            ':enabled' => post('is_enabled', '1') === '1' ? 1 : 0,
        ]);
        $message = 'RSSを追加しました。';
    }
}

$sites = db()->query('SELECT id,name FROM partner_sites ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$rows = db()->query('SELECT pr.*, ps.name AS partner_name FROM partner_rss pr LEFT JOIN partner_sites ps ON ps.id=pr.partner_site_id ORDER BY pr.id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>RSS管理</h1>
  <?php if ($message): ?><p><?= e($message) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <label>パートナーサイト
      <select name="partner_site_id" required>
        <?php foreach ($sites as $site): ?><option value="<?= e((string)$site['id']) ?>"><?= e((string)$site['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Feed URL<input type="url" name="feed_url" required></label>
    <label>有効<select name="is_enabled"><option value="1">有効</option><option value="0">無効</option></select></label>
    <button type="submit">追加</button>
  </form>
  <table class="admin-table"><tr><th>ID</th><th>サイト</th><th>Feed URL</th><th>状態</th></tr>
  <?php foreach ($rows as $r): ?><tr><td><?= e((string)$r['id']) ?></td><td><?= e((string)$r['partner_name']) ?></td><td><?= e((string)$r['feed_url']) ?></td><td><?= (int)$r['is_enabled']===1?'有効':'無効' ?></td></tr><?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
