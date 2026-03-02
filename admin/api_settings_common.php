<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/app.php';

/** @var string $apiType */
/** @var string $pageTitle */
/** @var string $testButtonLabel */
/** @var callable $testRunner */

if (!isset($apiType, $pageTitle, $testButtonLabel, $testRunner)) {
    throw new RuntimeException('api settings page variables are not initialized.');
}

auth_require_admin();

$title = $pageTitle;
$message = '';
$messageType = 'success';
$cred = api_credential_get($apiType);
$apiId = (string)($cred['api_id'] ?? '');
$affiliateId = (string)($cred['affiliate_id'] ?? '');
$testResult = null;

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
            $client = new DmmApiClient($apiId, $affiliateId, app_config()['dmm']['endpoint']);
            $testResult = $testRunner($client);
            $message = 'テスト取得に成功しました。';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'テスト取得に失敗しました: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1><?= e($pageTitle) ?></h1>
  <p>このページは <?= e($pageTitle) ?> 用の APIID / アフィリエイトID を個別に保存します。</p>

  <?php if ($message !== ''): ?>
    <div class="admin-notice <?= $messageType === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>"><p><?= e($message) ?></p></div>
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
    </div>
  </form>

  <?php if (is_array($testResult)): ?>
    <h2>テスト結果</h2>
    <pre style="white-space:pre-wrap;background:#111;color:#f3f3f3;padding:12px;border-radius:6px;"><?= e((string)json_encode($testResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
