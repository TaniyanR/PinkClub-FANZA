<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/app.php';
auth_require_admin();

$title = 'API設定';
$resultMessage = null;
$resultType = 'success';
$testTitles = [];
$testListTitle = '';
$settings = settings_get();

$floorOptions = [];
try {
    $sql = 'SELECT f.service_code,f.floor_code,f.name AS floor_name,s.name AS service_name,si.name AS site_name
            FROM dmm_floors f
            LEFT JOIN dmm_services s ON s.service_code = f.service_code
            LEFT JOIN dmm_sites si ON si.site_code = s.site_code
            ORDER BY si.name ASC, s.name ASC, f.name ASC';
    $stmt = db()->query($sql);
    $floorOptions = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $floorOptions = [];
}

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
            'fanza_site' => settings_normalize_site((string)post('site', 'FANZA')),
            'fanza_service' => strtolower(settings_normalize_token((string)post('service', 'digital'), 'digital')),
            'fanza_floor' => strtolower(settings_normalize_token((string)post('floor', 'videoa'), 'videoa')),
            'master_floor_id' => trim((string)post('master_floor_id', '43')),
            'item_sync_batch' => (string)$batch,
            'item_sync_enabled' => post('item_sync_enabled', '0') === '1' ? '1' : '0',
            'item_sync_interval_minutes' => (string)max(1, (int)post('item_sync_interval_minutes', 60)),
        ]);
        $resultMessage = 'API設定を保存しました。';
    }

    $masterActionMap = [
        'test_actresses' => ['kind' => 'actress', 'offset_key' => 'actress_sync_test_offset', 'table' => 'actresses', 'label' => '女優検索API'],
        'test_genres' => ['kind' => 'genre', 'offset_key' => 'genre_sync_test_offset', 'table' => 'genres', 'label' => 'ジャンル検索API'],
        'test_makers' => ['kind' => 'maker', 'offset_key' => 'maker_sync_test_offset', 'table' => 'makers', 'label' => 'メーカー検索API'],
        'test_series' => ['kind' => 'series', 'offset_key' => 'series_sync_test_offset', 'table' => 'series_master', 'label' => 'シリーズ検索API'],
        'test_authors' => ['kind' => 'author', 'offset_key' => 'author_sync_test_offset', 'table' => 'authors', 'label' => '作者検索API'],
    ];

    if ($action === 'test_items' || isset($masterActionMap[$action])) {
        try {
            $cfg = settings_get();
            if ((string)$cfg['api_id'] === '' || (string)$cfg['affiliate_id'] === '') {
                throw new RuntimeException('API ID / アフィリエイトID を保存してから実行してください。');
            }

            if ($action === 'test_items') {
                $offset = max(1, settings_int('item_sync_test_offset', 1));
                $result = dmm_sync_service()->syncItemsBatch((string)$cfg['site'], (string)$cfg['service'], (string)$cfg['floor'], 10, $offset);
                $nextOffset = (int)($result['next_offset'] ?? ($offset + 10));
                site_setting_set_many(['item_sync_test_offset' => (string)$nextOffset]);

                $resultMessage = '商品情報API テスト取得完了: ' . (int)$result['synced_count'] . '件を保存しました。';
                $stmt = db()->query('SELECT title FROM items ORDER BY updated_at DESC LIMIT 5');
                $testTitles = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
                $testListTitle = '代表タイトル（商品情報API）';
            } else {
                $spec = $masterActionMap[$action];
                $offset = max(1, settings_int((string)$spec['offset_key'], 1));
                $floorId = (string)($cfg['master_floor_id'] ?? '43');
                $count = dmm_sync_service()->syncMaster((string)$spec['kind'], (string)$spec['kind'] === 'actress' ? null : $floorId, $offset, 10);
                $nextOffset = $offset + 10;
                if ($nextOffset > 50000) {
                    $nextOffset = 1;
                }
                site_setting_set_many([(string)$spec['offset_key'] => (string)$nextOffset]);

                $resultMessage = (string)$spec['label'] . ' テスト取得完了: ' . $count . '件を保存しました。';
                $stmt = db()->query('SELECT name FROM ' . $spec['table'] . ' ORDER BY updated_at DESC LIMIT 5');
                $testTitles = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
                $testListTitle = '代表名称（' . (string)$spec['label'] . '）';
            }

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
    <label>サービス（service）
      <input name="service" value="<?= e((string)($settings['service'] ?? 'digital')) ?>">
    </label>
    <label>フロア
      <?php if ($floorOptions !== []): ?>
        <select name="floor">
          <?php $currentFloor = (string)($settings['floor'] ?? 'videoa'); ?>
          <?php foreach ($floorOptions as $option): ?>
            <?php
              $floorCode = (string)($option['floor_code'] ?? '');
              $serviceCode = (string)($option['service_code'] ?? '');
              $siteName = trim((string)($option['site_name'] ?? 'FANZA'));
              $serviceName = trim((string)($option['service_name'] ?? $serviceCode));
              $floorName = trim((string)($option['floor_name'] ?? $floorCode));
              $label = $siteName . ' - ' . $serviceName . ' - ' . $floorName;
            ?>
            <option value="<?= e($floorCode) ?>" data-service-code="<?= e($serviceCode) ?>" <?= $currentFloor === $floorCode ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input name="floor" value="<?= e((string)($settings['floor'] ?? 'videoa')) ?>">
      <?php endif; ?>
    </label>
    <label>floor_id（Genre/Maker/Series/Author）
      <input name="master_floor_id" value="<?= e((string)($settings['master_floor_id'] ?? '43')) ?>">
    </label>
    <label>商品取得件数
      <select name="item_sync_batch">
        <?php foreach ([100, 200, 300, 500, 1000] as $option): ?>
          <option value="<?= $option ?>" <?= ((int)($settings['item_sync_batch'] ?? 100) === $option) ? 'selected' : '' ?>><?= $option ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>定期自動取得
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
      <button class="button-secondary" type="submit" name="action" value="test_items">商品情報APIを10件取得（手動）</button>
      <button class="button-secondary" type="submit" name="action" value="test_actresses">女優検索APIを10件取得</button>
      <button class="button-secondary" type="submit" name="action" value="test_genres">ジャンル検索APIを10件取得</button>
      <button class="button-secondary" type="submit" name="action" value="test_makers">メーカー検索APIを10件取得</button>
      <button class="button-secondary" type="submit" name="action" value="test_series">シリーズ検索APIを10件取得</button>
      <button class="button-secondary" type="submit" name="action" value="test_authors">作者検索APIを10件取得</button>
    </div>
  </form>

  <?php if ($testTitles !== []): ?>
    <h2><?= e($testListTitle !== '' ? $testListTitle : '代表タイトル') ?></h2>
    <ul><?php foreach ($testTitles as $t): ?><li><?= e((string)$t) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
</section>

<script>
(function(){
  var floor=document.querySelector('select[name="floor"]');
  var service=document.querySelector('input[name="service"]');
  if(!floor||!service){return;}
  floor.addEventListener('change',function(){
    var opt=floor.options[floor.selectedIndex];
    var code=opt ? opt.getAttribute('data-service-code') : '';
    if(code){service.value=code;}
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
