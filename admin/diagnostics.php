<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/fanza_api_config.php';

auth_require_admin();

/** Mask a string: show only last 4 chars, rest replaced with * */
function mask_credential(string $value): string
{
    if ($value === '') {
        return '(未設定)';
    }
    $len = mb_strlen($value);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', $len - 4) . mb_substr($value, -4);
}

// ── POST: test sync ──────────────────────────────────────────────────────────
$syncResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail(post('_csrf'));

    $apiConfig = settings_get();
    $apiConfig['hits'] = 5;

    try {
        $syncResult = fanza_sync_items_to_db($apiConfig, 5);
        flash_set($syncResult['sync_ok'] ? 'success' : 'error',
            $syncResult['sync_ok']
                ? sprintf('テスト同期成功: 取得%d件 / 保存%d件', $syncResult['fetched_items_count'], $syncResult['saved_items_count'])
                : ('テスト同期失敗: ' . ($syncResult['reason'] ?: $syncResult['error_type']))
        );
    } catch (Throwable $e) {
        flash_set('error', 'テスト同期で例外が発生しました: ' . $e->getMessage());
    }

    app_redirect('admin/diagnostics.php');
}

// ── API config (masked) ──────────────────────────────────────────────────────
$settings   = settings_get();
$apiId      = (string)($settings['api_id'] ?? '');
$affiliateId = (string)($settings['affiliate_id'] ?? '');
$cfg        = app_config();
$endpoint   = (string)($cfg['dmm']['endpoint'] ?? '');
$site       = (string)($settings['site'] ?? '');
$service    = (string)($settings['service'] ?? '');
$floor      = (string)($settings['floor'] ?? '');

// ── DB connectivity & table counts ──────────────────────────────────────────
$dbOk     = false;
$dbError  = '';
$tables   = ['items', 'actresses', 'genres', 'makers', 'series_master', 'authors', 'api_logs', 'sync_logs'];
$counts   = [];
$tableLabels = [
    'items'         => '商品',
    'actresses'     => '女優',
    'genres'        => 'ジャンル',
    'makers'        => 'メーカー',
    'series_master' => 'シリーズ',
    'authors'       => '作者',
    'api_logs'      => 'APIログ',
    'sync_logs'     => '同期ログ',
];
try {
    $pdo  = db();
    $dbOk = true;
    foreach ($tables as $tbl) {
        try {
            $counts[$tbl] = (int)$pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        } catch (Throwable $e) {
            $counts[$tbl] = null;
        }
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// ── Latest sync logs ─────────────────────────────────────────────────────────
$recentLogs = [];
if ($dbOk && isset($counts['sync_logs']) && $counts['sync_logs'] !== null) {
    try {
        $recentLogs = db()->query('SELECT * FROM sync_logs ORDER BY id DESC LIMIT 10')->fetchAll();
    } catch (Throwable $ignore) {}
}

$title = 'API診断';
require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>API &amp; DB 診断</h1>
  <p class="admin-form-note">APIキーのマスク表示、DB接続確認、直近の同期状況を確認できます。</p>
</section>

<!-- API Config -->
<section class="admin-card">
  <h2>FANZA API 設定</h2>
  <table class="admin-table">
    <tr><th style="width:200px">項目</th><th>値</th><th style="width:80px">状態</th></tr>
    <tr>
      <td>API ID</td>
      <td><code><?= e(mask_credential($apiId)) ?></code></td>
      <td><?= $apiId !== '' ? '<span style="color:#1a8c3b">✔ 設定済</span>' : '<span style="color:#d63638">✘ 未設定</span>' ?></td>
    </tr>
    <tr>
      <td>アフィリエイトID</td>
      <td><code><?= e(mask_credential($affiliateId)) ?></code></td>
      <td><?= $affiliateId !== '' ? '<span style="color:#1a8c3b">✔ 設定済</span>' : '<span style="color:#d63638">✘ 未設定</span>' ?></td>
    </tr>
    <tr>
      <td>エンドポイント</td>
      <td><code><?= e($endpoint) ?></code></td>
      <td><?= $endpoint !== '' ? '<span style="color:#1a8c3b">✔</span>' : '<span style="color:#d63638">✘</span>' ?></td>
    </tr>
    <tr>
      <td>サイト</td>
      <td><?= e($site) ?></td>
      <td></td>
    </tr>
    <tr>
      <td>サービス / フロア</td>
      <td><?= e($service) ?> / <?= e($floor) ?></td>
      <td></td>
    </tr>
  </table>
</section>

<!-- DB Status -->
<section class="admin-card">
  <h2>DB 接続 &amp; テーブル件数</h2>
  <?php if (!$dbOk): ?>
    <div class="admin-notice admin-notice--error"><p>DB接続失敗: <?= e($dbError) ?></p></div>
  <?php else: ?>
    <p style="color:#1a8c3b;margin:0 0 12px">✔ DB接続OK</p>
    <div class="admin-status-grid">
      <?php foreach ($tables as $tbl): ?>
        <article class="admin-card admin-status-card">
          <strong><?= e($tableLabels[$tbl] ?? $tbl) ?></strong>
          <?php if ($counts[$tbl] === null): ?>
            <p style="color:#d63638;font-size:14px">テーブル無し</p>
          <?php else: ?>
            <p><?= e((string)$counts[$tbl]) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Test sync -->
<section class="admin-card admin-card--form">
  <h2>テスト同期 (5件)</h2>
  <p class="admin-form-note">APIから最大5件を取得してDBへ保存します。APIキーが設定されていないと失敗します。</p>
  <?php if ($apiId === '' || $affiliateId === ''): ?>
    <div class="admin-notice admin-notice--error"><p>API ID またはアフィリエイトIDが未設定のため実行できません。<a href="<?= e(admin_url('api_settings_common.php')) ?>">API設定ページ</a>で設定してください。</p></div>
  <?php else: ?>
    <form method="post">
      <?= csrf_input() ?>
      <div class="admin-actions">
        <button type="submit">テスト同期を実行</button>
      </div>
    </form>
  <?php endif; ?>
</section>

<!-- Recent sync logs -->
<?php if (!empty($recentLogs)): ?>
<section class="admin-card">
  <h2>直近の同期ログ</h2>
  <table class="admin-table">
    <tr><th>ID</th><th>種別</th><th>成否</th><th>件数</th><th>メッセージ</th><th>日時</th></tr>
    <?php foreach ($recentLogs as $log): ?>
      <tr>
        <td><?= e((string)$log['id']) ?></td>
        <td><?= e((string)$log['sync_type']) ?></td>
        <td><?= $log['is_success'] ? '<span style="color:#1a8c3b">OK</span>' : '<span style="color:#d63638">NG</span>' ?></td>
        <td><?= e((string)$log['synced_count']) ?></td>
        <td><?= e((string)$log['message']) ?></td>
        <td><?= e((string)$log['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
