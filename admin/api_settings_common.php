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

    if ($action === 'test_save') {
        try {
            $apiId = trim((string)post('api_id', $apiId));
            $affiliateId = trim((string)post('affiliate_id', $affiliateId));
            api_credential_set($apiType, $apiId, $affiliateId);
            $sync = dmm_sync_service($apiType);

            $s = settings_get();
            $beforeCount = (int)(db()->query('SELECT COUNT(*) FROM items')->fetchColumn() ?: 0);
            $offset = $beforeCount + 1;
            $count = $sync->syncItems(
                (string)$s['site'],
                (string)$s['service'],
                (string)$s['floor'],
                ['hits' => 10, 'offset' => $offset]
            );
            $afterCount = (int)(db()->query('SELECT COUNT(*) FROM items')->fetchColumn() ?: 0);
            $increased = $afterCount - $beforeCount;

            $message = 'テスト取得データを保存しました。取得件数: ' . (string)$count . ' / 新規保存: ' . (string)$increased;
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '保存に失敗しました: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

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
      <button type="submit" name="action" value="test_save" class="button-secondary"><?= e($testButtonLabel) ?>して保存</button>
    </div>
  </form>

</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
