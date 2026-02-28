<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$title = 'API設定';
$resultMessage = null;
$resultType = 'success';
$testTitles = [];
$settings = settings_get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $action = (string)post('action', 'save');

    if ($action === 'save') {
        $batch = (int)post('item_sync_batch', 100);
        $allowed = [100, 200, 300, 500, 1000];
        if (!in_array($batch, $allowed, true)) {
            $batch = 100;
        }
        site_setting_set_many([
            'fanza_api_id' => trim((string)post('api_id', '')),
            'fanza_affiliate_id' => trim((string)post('affiliate_id', '')),
            'item_sync_batch' => (string)$batch,
            'item_sync_enabled' => post('item_sync_enabled', '0') === '1' ? '1' : '0',
            'item_sync_interval_minutes' => (string)max(1, (int)post('item_sync_interval_minutes', 60)),
        ]);
        $resultMessage = 'API設定を保存しました。';
    }

    if ($action === 'test_items') {
        try {
            $result = dmm_sync_service()->syncItemsBatch('digital', 'videoa', 10, 1);
            $resultMessage = 'テスト取得完了: ' . (int)$result['synced_count'] . '件を保存しました。';
            $stmt = db()->query('SELECT title FROM items ORDER BY updated_at DESC LIMIT 5');
            $testTitles = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $resultType = 'success';
        } catch (Throwable $e) {
            $resultType = 'error';
            $resultMessage = 'テスト取得に失敗しました: ' . $e->getMessage();
        }
    }

    $settings = settings_get();
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>API設定</h1>
  <?php if ($resultMessage !== null): ?><div class="admin-notice <?= $resultType === 'error' ? 'admin-notice--error' : 'admin-notice--success' ?>"><p><?= e($resultMessage) ?></p></div><?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <label>APIID
      <input name="api_id" value="<?= e((string)($settings['api_id'] ?? '')) ?>">
    </label>
    <label>アフィリエイトID
      <input name="affiliate_id" value="<?= e((string)($settings['affiliate_id'] ?? '')) ?>">
    </label>
    <label>商品取得件数
      <select name="item_sync_batch">
        <?php foreach ([100, 200, 300, 500, 1000] as $option): ?>
          <option value="<?= $option ?>" <?= ((int)($settings['item_sync_batch'] ?? 100) === $option) ? 'selected' : '' ?>><?= $option ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>タイマー自動取得
      <select name="item_sync_enabled">
        <option value="1" <?= ((int)($settings['item_sync_enabled'] ?? 0) === 1) ? 'selected' : '' ?>>ON</option>
        <option value="0" <?= ((int)($settings['item_sync_enabled'] ?? 0) !== 1) ? 'selected' : '' ?>>OFF</option>
      </select>
    </label>
    <label>実行間隔（分）
      <input name="item_sync_interval_minutes" type="number" min="1" value="<?= e((string)($settings['item_sync_interval_minutes'] ?? 60)) ?>">
    </label>
    <div class="admin-actions">
      <button type="submit" name="action" value="save">保存</button>
      <button class="button-secondary" type="submit" name="action" value="test_items">商品情報を10件取得（手動）</button>
    </div>
  </form>

  <div class="admin-card" style="margin-top:16px;">
    <h2>タイマー実行状態</h2>
    <p>最終実行: <span id="timer-last"><?= e((string)($settings['last_item_sync_at'] ?? '未実行')) ?></span></p>
    <p>直近結果: <span id="timer-message">待機中</span></p>
  </div>

  <?php if ($testTitles !== []): ?>
    <h2>代表タイトル</h2>
    <ul><?php foreach ($testTitles as $t): ?><li><?= e((string)$t) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
</section>
<script>
(() => {
  const enabled = <?= ((int)($settings['item_sync_enabled'] ?? 0) === 1) ? 'true' : 'false' ?>;
  if (!enabled) return;
  const csrf = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const msgEl = document.getElementById('timer-message');
  const lastEl = document.getElementById('timer-last');

  const tick = async () => {
    try {
      const body = new URLSearchParams();
      body.set('_csrf', csrf);
      const res = await fetch('<?= e(admin_url('timer_tick.php')) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString(),
      });
      const json = await res.json();
      msgEl.style.color = json.ran ? '#0a7d2a' : '#333';
      msgEl.textContent = json.message || 'OK';
      if (json.at) lastEl.textContent = json.at;
    } catch (error) {
      msgEl.style.color = '#b42318';
      msgEl.textContent = 'タイマー通信に失敗しました。';
    }
  };

  setInterval(tick, 60000);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
