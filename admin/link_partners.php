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
        db()->prepare('INSERT INTO partner_sites(name,ref_code,url,is_enabled,created_at,updated_at) VALUES(:name,:ref,:url,:enabled,NOW(),NOW())')
            ->execute([
                ':name' => trim((string)post('name', '')),
                ':ref' => trim((string)post('ref_code', '')),
                ':url' => trim((string)post('url', '')),
                ':enabled' => post('is_enabled', '1') === '1' ? 1 : 0,
            ]);
        $message = '相互リンクを追加しました。';
    } elseif ($action === 'toggle') {
        db()->prepare('UPDATE partner_sites SET is_enabled = :enabled, updated_at = NOW() WHERE id = :id')
            ->execute([':enabled' => post('is_enabled', '0') === '1' ? 1 : 0, ':id' => (int)post('id', 0)]);
        $message = '状態を更新しました。';
    }
}

$rows = db()->query('SELECT * FROM partner_sites ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>相互リンク管理</h1>
  <?php if ($message): ?><p><?= e($message) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="create">
    <label>サイト名<input name="name" required></label>
    <label>refコード<input name="ref_code" required></label>
    <label>URL<input name="url" type="url" required></label>
    <label>有効
      <select name="is_enabled"><option value="1">有効</option><option value="0">無効</option></select>
    </label>
    <button type="submit">追加</button>
  </form>

  <table class="admin-table">
    <tr><th>ID</th><th>サイト名</th><th>ref</th><th>URL</th><th>状態</th><th>操作</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e((string)$r['id']) ?></td><td><?= e((string)$r['name']) ?></td><td><?= e((string)$r['ref_code']) ?></td><td><?= e((string)$r['url']) ?></td>
        <td><?= (int)$r['is_enabled'] === 1 ? '有効' : '無効' ?></td>
        <td>
          <form method="post">
            <?= csrf_input() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
            <input type="hidden" name="is_enabled" value="<?= (int)$r['is_enabled'] === 1 ? '0' : '1' ?>">
            <button type="submit" class="button-secondary"><?= (int)$r['is_enabled'] === 1 ? '無効化' : '有効化' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
