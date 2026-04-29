<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/app.php';

/** @var string $pageTitle */
/** @var string $testButtonLabel */

if (!isset($pageTitle)) {
    throw new RuntimeException('api settings page title is not initialized.');
}

auth_require_admin();

$apiType = 'items';
$title = $pageTitle;
$testButtonLabel = (string)($testButtonLabel ?? '商品情報を10件テスト取得');
$message = '';
$messageType = 'success';
$cred = api_credential_get($apiType);
$apiId = (string)($cred['api_id'] ?? '');
$affiliateId = (string)($cred['affiliate_id'] ?? '');
$testResult = null;
$savedRows = [];

$saveTargets = [
    'items' => ['table' => 'items', 'label' => '商品', 'id_column' => 'id', 'name_column' => 'title'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $action = (string)post('action', 'save');

    if ($action === 'save') {
        $apiId = trim((string)post('api_id', ''));
        $affiliateId = trim((string)post('affiliate_id', ''));
        api_credential_set($apiType, $apiId, $affiliateId);
        $message = '設定を保存しました。';
        $messageType = 'success';
    }

    if ($action === 'test') {
        try {
            $apiId = trim((string)post('api_id', $apiId));
            $affiliateId = trim((string)post('affiliate_id', $affiliateId));
            api_credential_set($apiType, $apiId, $affiliateId);
            $client = new DmmApiClient($apiId, $affiliateId, app_config()['dmm']['endpoint']);
            $s = settings_get();
            $testResult = $client->fetchItems(
                (string)$s['site'],
                (string)$s['service'],
                (string)$s['floor'],
                ['hits' => 10, 'offset' => 1]
            );
            $sync = dmm_sync_service($apiType);
            $count = $sync->syncItems(
                (string)$s['site'],
                (string)$s['service'],
                (string)$s['floor'],
                ['hits' => 100, 'offset' => 1]
            );

            $message = 'テスト取得と保存に成功しました。件数: ' . (string)$count;
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'テスト取得または保存に失敗しました: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'test_save') {
        try {
            $apiId = trim((string)post('api_id', $apiId));
            $affiliateId = trim((string)post('affiliate_id', $affiliateId));
            api_credential_set($apiType, $apiId, $affiliateId);
            $sync = dmm_sync_service($apiType);

            $s = settings_get();
            $count = $sync->syncItems(
                (string)$s['site'],
                (string)$s['service'],
                (string)$s['floor'],
                ['hits' => 100, 'offset' => 1]
            );

            $message = 'テスト取得データを保存しました。件数: ' . (string)$count;
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '保存に失敗しました: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($action === 'delete_row') {
        $target = $saveTargets[$apiType] ?? null;
        if (is_array($target)) {
            $id = (int)post('row_id', 0);
            if ($id > 0) {
                db()->prepare('DELETE FROM ' . $target['table'] . ' WHERE ' . $target['id_column'] . ' = :id')->execute([':id' => $id]);
                $message = $target['label'] . 'を削除しました。';
                $messageType = 'success';
            }
        }
    }
}

$target = $saveTargets[$apiType] ?? null;
if (is_array($target)) {
    $stmt = db()->query(
        'SELECT ' . $target['id_column'] . ' AS row_id, ' . $target['name_column'] . ' AS row_name, updated_at
         FROM ' . $target['table'] . '
         ORDER BY ' . $target['id_column'] . ' DESC
         LIMIT 50'
    );
    $savedRows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1><?= e($pageTitle) ?></h1>
  <p>このページで保存した APIID / アフィリエイトID は、商品・ジャンル・女優・シリーズの同期で共通利用されます。</p>

  <?php if ($message !== ''): ?>
    <div class="admin-notice <?= $messageType === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>">
      <p><?= e($message) ?></p>
    </div>
  <?php endif; ?>

  <form method="post" class="stack" style="max-width:700px;">
    <?= csrf_input() ?>
    <div>
      <label>APIID<br><input type="text" name="api_id" value="<?= e($apiId) ?>" style="width:100%"></label>
    </div>
    <div>
      <label>アフィリエイトID<br><input type="text" name="affiliate_id" value="<?= e($affiliateId) ?>" style="width:100%"></label>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <button type="submit" name="action" value="save">保存</button>
      <button type="submit" name="action" value="test" class="button-secondary"><?= e($testButtonLabel) ?></button>
      <button type="submit" name="action" value="test_save" class="button-secondary"><?= e($testButtonLabel) ?>して保存</button>
    </div>
  </form>

  <?php if (is_array($testResult)): ?>
    <h2>テスト結果</h2>
    <pre style="white-space:pre-wrap;background:#111;color:#f3f3f3;padding:12px;border-radius:6px;"><?= e((string)json_encode($testResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
  <?php endif; ?>

  <?php if (is_array($target)): ?>
    <h2>保存済み<?= e($target['label']) ?>（最新50件）</h2>
    <table class="admin-table">
      <tr><th>ID</th><th>名称</th><th>更新日時</th><th>操作</th></tr>
      <?php foreach ($savedRows as $row): ?>
        <tr>
          <td><?= e((string)($row['row_id'] ?? '')) ?></td>
          <td><?= e((string)($row['row_name'] ?? '')) ?></td>
          <td><?= e((string)($row['updated_at'] ?? '')) ?></td>
          <td>
            <form method="post">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="delete_row">
              <input type="hidden" name="row_id" value="<?= e((string)($row['row_id'] ?? '0')) ?>">
              <button type="submit" class="button-secondary">削除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
