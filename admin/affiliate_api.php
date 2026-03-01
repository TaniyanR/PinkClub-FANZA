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
            'fanza_site' => trim((string)post('site', 'FANZA')),
            'fanza_service' => trim((string)post('service', 'digital')),
            'fanza_floor' => trim((string)post('floor', 'videoa')),
            'master_floor_id' => trim((string)post('master_floor_id', '43')),
            'item_sync_batch' => (string)$batch,
            'item_sync_enabled' => '1',
            'item_sync_interval_minutes' => (string)max(1, (int)post('item_sync_interval_minutes', 60)),
        ]);

        try {
            $cfg = settings_get();
            if ((string)$cfg['api_id'] !== '' && (string)$cfg['affiliate_id'] !== '') {
                dmm_sync_service()->syncItemsBatch((string)$cfg['service'], (string)$cfg['floor'], 10, 1);
            }
            $resultMessage = 'API設定を保存しました。初回同期を実行しました。';
        } catch (Throwable $e) {
            $resultType = 'error';
            $resultMessage = 'API設定は保存しましたが、初回同期に失敗しました: ' . $e->getMessage();
        }
    }

    if ($action === 'test_items') {
        try {
            $cfg = settings_get();
            if ((string)$cfg['api_id'] === '' || (string)$cfg['affiliate_id'] === '') {
                throw new RuntimeException('API ID / アフィリエイトID を保存してから実行してください。');
            }
            $offset = max(1, settings_int('item_sync_test_offset', 1));
            $result = dmm_sync_service()->syncItemsBatch((string)$cfg['service'], (string)$cfg['floor'], 10, $offset);
            $nextOffset = (int)($result['next_offset'] ?? ($offset + 100));
            site_setting_set_many(['item_sync_test_offset' => (string)$nextOffset]);

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
  <?php if ((string)($settings['api_id'] ?? '') === '' || (string)($settings['affiliate_id'] ?? '') === ''): ?>
    <div class="admin-notice admin-notice--error"><p>API ID / アフィリエイトID が未設定です。保存してから同期してください。</p></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_input() ?>
    <label>APIID
      <input name="api_id" value="<?= e((string)($settings['api_id'] ?? '')) ?>">
    </label>
    <label>アフィリエイトID
      <input name="affiliate_id" value="<?= e((string)($settings['affiliate_id'] ?? '')) ?>">
    </label>
    <label>サイト
      <input name="site" value="<?= e((string)($settings['site'] ?? 'FANZA')) ?>">
    </label>
    <label>サービス（通常は digital）
      <input name="service" value="<?= e((string)($settings['service'] ?? 'digital')) ?>">
    </label>
    <label>フロア（動画は videoa）
      <input name="floor" value="<?= e((string)($settings['floor'] ?? 'videoa')) ?>">
    </label>
    <label>フロアID（Genre/Maker/Series/Author）
      <input name="master_floor_id" value="<?= e((string)($settings['master_floor_id'] ?? '43')) ?>">
    </label>
    <label>商品取得件数
      <select name="item_sync_batch">
        <?php foreach ([100, 200, 300, 500, 1000] as $option): ?>
          <option value="<?= $option ?>" <?= ((int)($settings['item_sync_batch'] ?? 100) === $option) ? 'selected' : '' ?>><?= $option ?></option>
        <?php endforeach; ?>
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

  <?php if ($testTitles !== []): ?>
    <h2>代表タイトル</h2>
    <ul><?php foreach ($testTitles as $t): ?><li><?= e((string)$t) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
