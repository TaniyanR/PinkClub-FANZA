<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/scheduler.php';
auth_require_admin();

$title = 'API設定';
$resultMessage = null;
$settings = settings_get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail(post('_csrf'));
    $action = (string)post('action', 'save');

    if ($action === 'save') {
        $batch = (int)post('item_sync_batch', 100);
        $masterFloorId = trim((string)post('master_floor_id', ''));
        settings_save(trim((string)post('api_id', '')), trim((string)post('affiliate_id', '')), $batch, $masterFloorId !== '' ? (int)$masterFloorId : null);
        $resultMessage = 'API設定を保存しました。';
    } elseif ($action === 'test_items') {
        try {
            $result = dmm_sync_service()->syncItemsBatch('digital', 'videoa', 10, 1);
            $resultMessage = '商品同期テスト完了: ' . (int)$result['synced_count'] . '件';
        } catch (Throwable $e) {
            $resultMessage = '商品同期テスト失敗: ' . $e->getMessage();
        }
    } elseif ($action === 'tick_now') {
        $tick = scheduler_tick();
        $resultMessage = 'タイマー式同期実行: ' . json_encode($tick, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    <label>商品同期の1回あたり件数
      <select name="item_sync_batch">
        <?php foreach ([100,200,300,500,1000] as $option): ?>
          <option value="<?= $option ?>" <?= ((int)($settings['item_sync_batch'] ?? 100) === $option) ? 'selected' : '' ?>><?= $option ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>マスタ同期 floor_id（Genre/Maker/Series/Author用）
      <input name="master_floor_id" value="<?= e((string)($settings['master_floor_id'] ?? '')) ?>" placeholder="例: 43">
    </label>
    <div class="admin-actions">
      <button type="submit" name="action" value="save">保存</button>
      <button class="button-secondary" type="submit" name="action" value="test_items">商品同期テスト（10件）</button>
      <button class="button-secondary" type="submit" name="action" value="tick_now">タイマー式同期を今すぐ1回走らせる</button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
