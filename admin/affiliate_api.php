<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/scheduler.php';
auth_require_admin();

$title = 'API設定';
$resultMessage = null;
$testTitles = [];
$settings = settings_get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail(post('_csrf'));
    $action = (string)post('action', 'save');

    if ($action === 'save') {
        $batch = (int)post('item_sync_batch', 100);
        $masterFloorId = trim((string)post('master_floor_id', ''));
        settings_save(trim((string)post('api_id', '')), trim((string)post('affiliate_id', '')), $batch, $masterFloorId !== '' ? (int)$masterFloorId : null);
        $resultMessage = 'API設定を保存しました。';
    }

    if ($action === 'test_items') {
        try {
            $result = dmm_sync_service()->syncItemsBatch('digital', 'videoa', 10, 1);
            $resultMessage = 'テスト取得完了: ' . (int)$result['synced_count'] . '件を保存しました。';
            $stmt = db()->query('SELECT title FROM items ORDER BY updated_at DESC LIMIT 5');
            $testTitles = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Throwable $e) {
            $resultMessage = 'テスト取得に失敗しました: ' . $e->getMessage();
        }
    }

    $settings = settings_get();
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>アフィリエイト設定 / API設定</h1>
  <?php if ($resultMessage !== null): ?><p><?= e($resultMessage) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <label>API ID
      <input name="api_id" value="<?= e((string)($settings['api_id'] ?? '')) ?>">
    </label>
    <label>Affiliate ID
      <input name="affiliate_id" value="<?= e((string)($settings['affiliate_id'] ?? '')) ?>">
    </label>
    <label>商品取得件数
      <select name="item_sync_batch">
        <?php foreach ([100,200,300,500,1000] as $option): ?>
          <option value="<?= $option ?>" <?= ((int)($settings['item_sync_batch'] ?? 100) === $option) ? 'selected' : '' ?>><?= $option ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>マスタ同期 floor_id（任意）
      <input name="master_floor_id" value="<?= e((string)($settings['master_floor_id'] ?? '')) ?>" placeholder="例: 43">
    </label>
    <div class="admin-actions">
      <button type="submit" name="action" value="save">保存</button>
      <button class="button-secondary" type="submit" name="action" value="test_items">テスト取得（10件）</button>
    </div>
  </form>
  <?php if ($testTitles !== []): ?>
    <h2>代表タイトル</h2>
    <ul><?php foreach ($testTitles as $t): ?><li><?= e((string)$t) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
