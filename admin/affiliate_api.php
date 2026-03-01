<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';

// app共通（db(), settings_get(), auth_require_admin(), csrf_* など）
require_once __DIR__ . '/../lib/app.php';

// FANZA系ヘルパ（fanza_normalize_api_config, fanza_test_*, fanza_sync_items_to_db, fanza_api_timeout_config 等）
require_once __DIR__ . '/../lib/fanza_api_config.php';

auth_require_admin();

$title = 'API設定';
$resultMessage = null;
$resultType = 'success';
$testTitles = [];
$masterTestResults = [];
$syncSummary = null;
$connectionSummary = null;
$settings = settings_get();

/**
 * マスタAPIの10件テスト取得を実行し、表示しやすい配列へ整形する。
 */
function run_master_test_fetch(string $kind, array $cfg): array
{
    $labels = [
        'actress' => '女優検索API',
        'genre' => 'ジャンル検索API',
        'maker' => 'メーカー検索API',
        'series' => 'シリーズ検索API',
        'author' => '作者検索API',
    ];

    $responseKeys = [
        'actress' => 'actress',
        'genre' => 'genre',
        'maker' => 'maker',
        'series' => 'series',
        'author' => 'author',
    ];

    $nameKeys = [
        'actress' => ['name', 'ruby'],
        'genre' => ['name', 'ruby'],
        'maker' => ['name', 'ruby'],
        'series' => ['name', 'ruby'],
        'author' => ['name', 'ruby'],
    ];

    if (!isset($labels[$kind])) {
        throw new InvalidArgumentException('不明なAPI種別です: ' . $kind);
    }

    $floorId = trim((string)($cfg['master_floor_id'] ?? '43'));
    $params = [
        'hits' => 10,
        'offset' => 1,
    ];
    if ($kind !== 'actress') {
        $params['floor_id'] = $floorId;
    }

    $response = match ($kind) {
        'actress' => dmm_client_from_settings()->searchActresses($params),
        'genre' => dmm_client_from_settings()->searchGenres($params),
        'maker' => dmm_client_from_settings()->searchMakers($params),
        'series' => dmm_client_from_settings()->searchSeries($params),
        'author' => dmm_client_from_settings()->searchAuthors($params),
        default => throw new InvalidArgumentException('不明なAPI種別です: ' . $kind),
    };

    $result = $response['result'] ?? [];
    $rows = $result[$responseKeys[$kind]] ?? [];
    if (!is_array($rows)) {
        $rows = [];
    }
    if (array_is_list($rows) === false && isset($rows['name'])) {
        $rows = [$rows];
    }

    $sampleNames = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string)($row[$nameKeys[$kind][0]] ?? ''));
        $ruby = trim((string)($row[$nameKeys[$kind][1]] ?? ''));
        if ($name === '') {
            continue;
        }
        $sampleNames[] = $ruby !== '' ? ($name . '（' . $ruby . '）') : $name;
        if (count($sampleNames) >= 10) {
            break;
        }
    }

    return [
        'kind' => $kind,
        'label' => $labels[$kind],
        'ok' => true,
        'status' => (string)($result['status'] ?? '200'),
        'count' => count($sampleNames),
        'floor_id' => $kind === 'actress' ? '-' : $floorId,
        'names' => $sampleNames,
    ];
}

/**
 * 念のため：正規化関数が lib 側に無い場合でも落ちないようにフォールバック定義
 * （既に定義されている環境ではこちらは使われません）
 */
if (!function_exists('settings_normalize_token')) {
    function settings_normalize_token(string $value, string $default = ''): string
    {
        $v = trim($value);
        if ($v === '') {
            return $default;
        }
        // 英数・_・- のみに制限（想定：service_code / floor_code）
        $v = preg_replace('/[^a-zA-Z0-9_-]/', '', $v) ?? '';
        return $v !== '' ? $v : $default;
    }
}

if (!function_exists('settings_normalize_site')) {
    function settings_normalize_site(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return 'FANZA';
        }
        // site は基本 "FANZA" 想定。変な文字は除去し、英数と _- のみ許可
        $v = preg_replace('/[^a-zA-Z0-9_-]/', '', $v) ?? 'FANZA';
        return $v !== '' ? $v : 'FANZA';
    }
}

$floorOptions = [];
try {
    $sql = 'SELECT f.service_code,f.floor_code,f.name AS floor_name,s.name AS service_name,si.name AS site_name
            FROM dmm_floors f
            LEFT JOIN dmm_services s ON s.service_code = f.service_code
            LEFT JOIN dmm_sites si ON si.site_code = s.site_code
            ORDER BY si.name ASC, s.name ASC, f.name ASC';
    $stmt = db()->query($sql);
    $floorOptions = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable) {
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
            // settings_get() 側で api_id / affiliate_id に正規化される前提（保存キーは fanza_*）
            'fanza_api_id' => trim((string)post('api_id', '')),
            'fanza_affiliate_id' => trim((string)post('affiliate_id', '')),

            // ここは入力を正規化して保存（安全・一貫性）
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

    if ($action === 'test_connection') {
        try {
            $cfg = fanza_normalize_api_config(settings_get());
            $timeouts = fanza_api_timeout_config($cfg);

            $credentialTest = fanza_test_api_credentials(
                (string)$cfg['api_id'],
                (string)$cfg['affiliate_id'],
                $timeouts['connect_timeout'],
                $timeouts['timeout']
            );

            $itemTest = fanza_test_item_fetch(
                (string)$cfg['api_id'],
                (string)$cfg['affiliate_id'],
                (string)$cfg['service'],
                (string)$cfg['floor'],
                $timeouts['connect_timeout'],
                $timeouts['timeout']
            );

            $connectionSummary = [
                'credential' => $credentialTest,
                'item' => $itemTest,
            ];

            $allOk = (($credentialTest['ok'] ?? false) && ($itemTest['ok'] ?? false));

            // 現在の items 件数
            $itemsCount = 0;
            try {
                $itemsCount = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
            } catch (Throwable) {
                $itemsCount = 0;
            }

            if ($allOk) {
                if ($itemsCount === 0) {
                    // 初回導線（未同期なら 10件だけ自動同期して体験を良くする）
                    $autoSync = dmm_sync_service()->syncItemsBatch((string)($cfg['site'] ?? 'FANZA'), (string)$cfg['service'], (string)$cfg['floor'], 10, 1);
                    $autoSynced = (int)($autoSync['synced_count'] ?? 0);

                    $resultType = $autoSynced > 0 ? 'success' : 'error';
                    $resultMessage = $autoSynced > 0
                        ? '接続テストに成功し、未同期のため商品を自動同期しました（' . $autoSynced . '件）。'
                        : '接続テストには成功しましたが、自動同期で0件でした。フロア設定またはAPI結果をご確認ください。';
                } else {
                    $resultType = 'success';
                    $resultMessage = '接続テストに成功しました。';
                }
            } else {
                // FANZA直APIがNGでも、未同期なら既存同期ロジックで試す（救済）
                $fallbackSynced = 0;
                if ($itemsCount === 0) {
                    try {
                        $fallback = dmm_sync_service()->syncItemsBatch((string)($cfg['site'] ?? 'FANZA'), (string)$cfg['service'], (string)$cfg['floor'], 10, 1);
                        $fallbackSynced = (int)($fallback['synced_count'] ?? 0);
                    } catch (Throwable $fallbackError) {
                        $connectionSummary['item']['fallback_error'] = $fallbackError->getMessage();
                    }
                }

                if ($fallbackSynced > 0) {
                    $resultType = 'success';
                    $resultMessage = '接続テスト（FANZA直API）は失敗しましたが、既存同期ロジックで ' . $fallbackSynced . '件保存できました。フロント表示は可能です。';
                } else {
                    $resultType = 'error';
                    $resultMessage = '接続テストでエラーが発生しました。HTTP 400 の場合は API ID/アフィリエイトID の組み合わせ、または floor/service をご確認ください。';
                }
            }
        } catch (Throwable $e) {
            $resultType = 'error';
            $resultMessage = '接続テストに失敗しました: ' . $e->getMessage();
        }
    }

    if ($action === 'test_items') {
        try {
            $cfg = settings_get();
            if ((string)($cfg['api_id'] ?? '') === '' || (string)($cfg['affiliate_id'] ?? '') === '') {
                throw new RuntimeException('API ID / アフィリエイトID を保存してから実行してください。');
            }

            $offset = max(1, settings_int('item_sync_test_offset', 1));

            // syncItemsBatch の引数: (site, service, floor, limit, offset)
            $result = dmm_sync_service()->syncItemsBatch(
                (string)($cfg['site'] ?? 'FANZA'),
                (string)$cfg['service'],
                (string)$cfg['floor'],
                10,
                $offset
            );

            $nextOffset = (int)($result['next_offset'] ?? ($offset + 100));
            site_setting_set_many(['item_sync_test_offset' => (string)$nextOffset]);

            $resultMessage = 'テスト取得完了: ' . (int)($result['synced_count'] ?? 0) . '件を保存しました。';

            $stmt = db()->query('SELECT title FROM items ORDER BY updated_at DESC LIMIT 5');
            $testTitles = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

            $resultType = 'success';
        } catch (Throwable $e) {
            $resultType = 'error';
            $resultMessage = 'テスト取得に失敗しました: ' . $e->getMessage();
        }
    }

    if ($action === 'test_master_api') {
        try {
            $cfg = settings_get();
            if ((string)($cfg['api_id'] ?? '') === '' || (string)($cfg['affiliate_id'] ?? '') === '') {
                throw new RuntimeException('API ID / アフィリエイトID を保存してから実行してください。');
            }

            $kinds = ['actress', 'genre', 'maker', 'series', 'author'];
            $masterTestResults = [];
            foreach ($kinds as $kind) {
                $masterTestResults[] = run_master_test_fetch($kind, $cfg);
            }

            $resultType = 'success';
            $resultMessage = 'マスタAPIテスト取得が完了しました（各API 10件）。';
        } catch (Throwable $e) {
            $resultType = 'error';
            $resultMessage = 'マスタAPIテスト取得に失敗しました: ' . $e->getMessage();
        }
    }

    if ($action === 'sync_db') {
        try {
            $cfg = fanza_normalize_api_config(settings_get());
            $hits = max(10, min(100, (int)($cfg['item_sync_batch'] ?? 100)));

            $syncSummary = fanza_sync_items_to_db($cfg, $hits);

            // 0件保存なら、既存同期ロジックへフォールバック（保険）
            $savedItemsCount = (int)($syncSummary['saved_items_count'] ?? 0);
            if ($savedItemsCount <= 0) {
                $legacy = dmm_sync_service()->syncItemsBatch((string)($cfg['site'] ?? 'FANZA'), (string)$cfg['service'], (string)$cfg['floor'], $hits, 1);
                $legacySaved = (int)($legacy['synced_count'] ?? 0);

                $syncSummary['saved_items_count'] = $legacySaved;
                $syncSummary['fetched_items_count'] = max((int)($syncSummary['fetched_items_count'] ?? 0), $legacySaved);

                if (!isset($syncSummary['warnings']) || !is_array($syncSummary['warnings'])) {
                    $syncSummary['warnings'] = [];
                }
                $syncSummary['warnings'][] = 'FANZA同期で保存件数が0件だったため、既存同期ロジックへフォールバックしました。';

                if ($legacySaved > 0) {
                    $syncSummary['sync_ok'] = true;
                    $syncSummary['reason'] = '';
                    $syncSummary['error_type'] = '';
                }
            }

            $resultType = !empty($syncSummary['sync_ok']) ? 'success' : 'error';
            $resultMessage = !empty($syncSummary['sync_ok']) ? '同期実行が完了しました。' : '同期実行でエラーが発生しました。';
        } catch (Throwable $e) {
            $resultType = 'error';
            $resultMessage = '同期実行に失敗しました: ' . $e->getMessage();
        }
    }

    $settings = settings_get();
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card admin-card--form">
  <h1>API設定</h1>

  <?php if ($resultMessage !== null): ?>
    <div class="admin-notice <?= $resultType === 'error' ? 'admin-notice--error' : 'admin-notice--success' ?>">
      <p><?= e($resultMessage) ?></p>
    </div>
  <?php endif; ?>

  <?php if ((string)($settings['api_id'] ?? '') === '' || (string)($settings['affiliate_id'] ?? '') === ''): ?>
    <div class="admin-notice admin-notice--error">
      <p>API ID / アフィリエイトID が未設定です。保存してから同期してください。</p>
    </div>
  <?php endif; ?>

  <?php
    $currentItemsCount = 0;
    try {
        $currentItemsCount = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
    } catch (Throwable) {
        $currentItemsCount = 0;
    }
  ?>
  <div class="admin-notice <?= $currentItemsCount > 0 ? 'admin-notice--success' : 'admin-notice--error' ?>">
    <p>現在の items 件数: <?= e((string)$currentItemsCount) ?>件<?= $currentItemsCount === 0 ? '（未同期）' : '' ?></p>
  </div>

  <div class="admin-card" style="margin-bottom:12px;">
    <h2 style="margin-top:0;">XAMPP向け DB設定ヘルプ</h2>
    <ul>
      <li>host: <code>127.0.0.1</code> / port: <code>3306</code></li>
      <li>db: <code>pinkclub_fanza</code> / user: <code>root</code> / pass: （空）</li>
      <li><code>localhost</code> で接続できない場合は <code>127.0.0.1</code> を使用してください。</li>
      <li>phpMyAdminで DB名が <code>pinkclub_fanza</code> と一致しているか確認してください。</li>
    </ul>
  </div>

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
            <option
              value="<?= e($floorCode) ?>"
              data-service-code="<?= e($serviceCode) ?>"
              <?= $currentFloor === $floorCode ? 'selected' : '' ?>
            ><?= e($label) ?></option>
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
      <button class="button-secondary" type="submit" name="action" value="test_connection">接続テスト</button>
      <button class="button-secondary" type="submit" name="action" value="test_items">商品情報を10件取得（手動）</button>
      <button class="button-secondary" type="submit" name="action" value="test_master_api">マスタAPIを各10件テスト取得</button>
      <button type="submit" name="action" value="sync_db">同期実行（DB保存）</button>
    </div>
  </form>

  <?php if ($masterTestResults !== []): ?>
    <div class="admin-card" style="margin-top:12px;">
      <h2 style="margin-top:0;">マスタAPI テスト取得結果（各10件）</h2>
      <?php foreach ($masterTestResults as $testResult): ?>
        <div style="padding:8px 0;border-top:1px solid #eee;">
          <p style="margin:0 0 6px 0;"><strong><?= e((string)($testResult['label'] ?? '')) ?></strong> / status: <?= e((string)($testResult['status'] ?? '-')) ?> / 取得件数: <?= e((string)($testResult['count'] ?? 0)) ?> / floor_id: <?= e((string)($testResult['floor_id'] ?? '-')) ?></p>
          <?php if (!empty($testResult['names']) && is_array($testResult['names'])): ?>
            <ul style="margin:0;">
              <?php foreach ($testResult['names'] as $name): ?>
                <li><?= e((string)$name) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p style="margin:0;">表示可能なデータがありません。</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($connectionSummary !== null): ?>
    <div class="admin-card" style="margin-top:12px;">
      <h2 style="margin-top:0;">接続テスト結果</h2>
      <p>FloorList: <?= !empty($connectionSummary['credential']['ok']) ? 'OK' : 'NG' ?> / HTTP <?= e((string)($connectionSummary['credential']['http_code'] ?? '-')) ?></p>
      <p>ItemList: <?= !empty($connectionSummary['item']['ok']) ? 'OK' : 'NG' ?> / HTTP <?= e((string)($connectionSummary['item']['http_code'] ?? '-')) ?> / 件数 <?= e((string)($connectionSummary['item']['item_count'] ?? 0)) ?></p>
      <?php if (!empty($connectionSummary['credential']['message'])): ?><p>FloorList詳細: <?= e((string)$connectionSummary['credential']['message']) ?></p><?php endif; ?>
      <?php if (!empty($connectionSummary['item']['message'])): ?><p>ItemList詳細: <?= e((string)$connectionSummary['item']['message']) ?></p><?php endif; ?>
      <?php if (!empty($connectionSummary['item']['fallback_error'])): ?><p>fallback_error: <?= e((string)$connectionSummary['item']['fallback_error']) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($syncSummary !== null): ?>
    <div class="admin-card" style="margin-top:12px;">
      <h2 style="margin-top:0;">同期結果</h2>
      <p>結果: <?= !empty($syncSummary['sync_ok']) ? 'OK' : 'NG' ?></p>
      <p>対象floor: <?= e((string)($syncSummary['target_floor_label'] ?? '-')) ?></p>
      <p>HTTP/API status: HTTP <?= e((string)($syncSummary['http_status'] ?? '-')) ?> / <?= e((string)($syncSummary['error_type'] ?? '200')) ?></p>
      <p>取得件数: <?= e((string)($syncSummary['fetched_items_count'] ?? 0)) ?> / 保存件数: <?= e((string)($syncSummary['saved_items_count'] ?? 0)) ?></p>
      <p>reason: <?= e((string)($syncSummary['reason'] ?? '')) ?></p>
      <p>error_type: <?= e((string)($syncSummary['error_type'] ?? '')) ?></p>
      <?php if (!empty($syncSummary['warnings']) && is_array($syncSummary['warnings'])): ?>
        <p>warnings:</p>
        <ul>
          <?php foreach ($syncSummary['warnings'] as $warning): ?>
            <li><?= e((string)$warning) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($testTitles !== []): ?>
    <h2>代表タイトル</h2>
    <ul>
      <?php foreach ($testTitles as $t): ?>
        <li><?= e((string)$t) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<script>
(function(){
  var floor = document.querySelector('select[name="floor"]');
  var service = document.querySelector('input[name="service"]');
  if(!floor || !service){ return; }
  floor.addEventListener('change', function(){
    var opt = floor.options[floor.selectedIndex];
    var code = opt ? opt.getAttribute('data-service-code') : '';
    if(code){ service.value = code; }
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
