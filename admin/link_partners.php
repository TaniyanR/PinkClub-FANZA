<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
analytics_ensure_tables();
$title = '相互リンク管理';
$message = null;

try {
    db()->exec('ALTER TABLE partner_sites ADD COLUMN show_link TINYINT(1) NOT NULL DEFAULT 1');
} catch (Throwable $e) {
}
try {
    db()->exec('ALTER TABLE partner_rss ADD COLUMN show_rss TINYINT(1) NOT NULL DEFAULT 1');
} catch (Throwable $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $action = (string)post('action', 'create');
    if ($action === 'create') {
        $name = trim((string)post('name', ''));
        $url = trim((string)post('url', ''));
        $rssUrl = trim((string)post('rss_url', ''));
        $refCode = 'partner_' . substr(sha1($name . '|' . $url . '|' . microtime(true)), 0, 16);

        db()->prepare('INSERT INTO partner_sites(name,ref_code,url,is_enabled,show_link,created_at,updated_at) VALUES(:name,:ref,:url,1,:show_link,NOW(),NOW())')
            ->execute([
                ':name' => $name,
                ':ref' => $refCode,
                ':url' => $url,
                ':show_link' => post('show_link', '0') === '1' ? 1 : 0,
            ]);

        $siteId = (int)db()->lastInsertId();
        if ($siteId > 0 && $rssUrl !== '') {
            db()->prepare('INSERT INTO partner_rss(partner_site_id,feed_url,is_enabled,show_rss,created_at,updated_at) VALUES(:sid,:url,1,:show_rss,NOW(),NOW())')
                ->execute([
                    ':sid' => $siteId,
                    ':url' => $rssUrl,
                    ':show_rss' => post('show_rss', '0') === '1' ? 1 : 0,
                ]);
        }

        site_setting_set('link.sort_mode', post('sort_mode', 'registered') === 'kana' ? 'kana' : 'registered');
        $message = '相互リンクを追加しました。';
    } elseif ($action === 'toggle_link') {
        db()->prepare('UPDATE partner_sites SET show_link = :show, updated_at = NOW() WHERE id = :id')
            ->execute([':show' => post('show_link', '0') === '1' ? 1 : 0, ':id' => (int)post('id', 0)]);
        $message = '相互リンク表示を更新しました。';
    } elseif ($action === 'toggle_rss') {
        db()->prepare('UPDATE partner_rss SET show_rss = :show, updated_at = NOW() WHERE id = :id')
            ->execute([':show' => post('show_rss', '0') === '1' ? 1 : 0, ':id' => (int)post('rss_id', 0)]);
        $message = 'RSS表示を更新しました。';
    } elseif ($action === 'sort_mode') {
        site_setting_set('link.sort_mode', post('sort_mode', 'registered') === 'kana' ? 'kana' : 'registered');
        $message = '表示順設定を更新しました。';
    }
}

$sortMode = site_setting_get('link.sort_mode', 'registered');
$rows = db()->query('SELECT ps.*, pr.id AS rss_id, pr.feed_url, COALESCE(pr.show_rss, pr.is_enabled, 0) AS show_rss FROM partner_sites ps LEFT JOIN partner_rss pr ON pr.partner_site_id = ps.id ORDER BY ps.id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>相互リンク管理</h1>
  <?php if ($message): ?><p><?= e($message) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <label>サイト名<input name="name" required></label>
    <label>URL<input name="url" type="url" required></label>
    <label>RSS URL<input name="rss_url" type="url" placeholder="https://example.com/feed.xml"></label>
    <label><input type="checkbox" name="show_link" value="1" checked> 相互リンクを表示する</label>
    <label><input type="checkbox" name="show_rss" value="1" checked> RSSを表示する</label>
    <fieldset>
      <legend>表示順</legend>
      <label><input type="radio" name="sort_mode" value="registered" <?= $sortMode !== 'kana' ? 'checked' : '' ?>> 登録順</label>
      <label><input type="radio" name="sort_mode" value="kana" <?= $sortMode === 'kana' ? 'checked' : '' ?>> あいうえお順</label>
    </fieldset>
    <button type="submit">追加</button>
  </form>

  <table class="admin-table">
    <tr><th>ID</th><th>サイト名</th><th>URL</th><th>RSS</th><th>相互リンク表示</th><th>RSS表示</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e((string)$r['id']) ?></td><td><?= e((string)$r['name']) ?></td><td><?= e((string)$r['url']) ?></td>
        <td><?= e((string)($r['feed_url'] ?? '')) ?></td>
        <td>
          <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_link"><input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
            <label><input type="checkbox" name="show_link" value="1" <?= ((int)($r['show_link'] ?? 1) === 1) ? 'checked' : '' ?> onchange="this.form.submit()"></label>
          </form>
        </td>
        <td>
          <?php if ((int)($r['rss_id'] ?? 0) > 0): ?>
          <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="toggle_rss"><input type="hidden" name="rss_id" value="<?= e((string)$r['rss_id']) ?>">
            <label><input type="checkbox" name="show_rss" value="1" <?= ((int)($r['show_rss'] ?? 0) === 1) ? 'checked' : '' ?> onchange="this.form.submit()"></label>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="sort_mode">
    <label><input type="radio" name="sort_mode" value="registered" <?= $sortMode !== 'kana' ? 'checked' : '' ?>> 登録順</label>
    <label><input type="radio" name="sort_mode" value="kana" <?= $sortMode === 'kana' ? 'checked' : '' ?>> あいうえお順</label>
    <button type="submit" class="button-secondary">表示順を保存</button>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
