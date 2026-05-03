<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/app.php';

auth_require_admin();

$title = '自動設定';
$message = '';
$messageType = 'success';

$intervalOptions = [10, 20, 30, 60, 120, 180, 360, 720];
$batchOptions = [1, 10, 20, 30, 50, 100, 200, 300, 500];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));

    $enabled = post('item_sync_enabled', '0') === '1' ? '1' : '0';
    $interval = (int)post('item_sync_interval_minutes', 60);
    if (!in_array($interval, $intervalOptions, true)) {
        $interval = 60;
    }

    $batch = (int)post('item_sync_batch', 100);
    if (!in_array($batch, $batchOptions, true)) {
        $batch = 100;
    }

    $singleKeyword = trim((string)post('item_sync_keyword', ''));

    $compoundKeywords = [];
    for ($i = 1; $i <= 5; $i++) {
        $value = trim((string)post('item_sync_compound_' . $i, ''));
        if ($value !== '') {
            $compoundKeywords[] = $value;
        }
    }

    $excludeKeywords = [];
    for ($i = 1; $i <= 5; $i++) {
        $value = trim((string)post('item_sync_exclude_' . $i, ''));
        if ($value !== '') {
            $excludeKeywords[] = $value;
        }
    }

    site_setting_set_many([
        'item_sync_enabled' => $enabled,
        'item_sync_interval_minutes' => (string)$interval,
        'item_sync_batch' => (string)$batch,
        'item_sync_keyword' => $singleKeyword,
        'item_sync_compound_keywords' => implode("\n", $compoundKeywords),
        'item_sync_exclude_keywords' => implode("\n", $excludeKeywords),
    ]);

    $message = '自動設定を保存しました。';
}

$settings = settings_get();
$currentInterval = (int)($settings['item_sync_interval_minutes'] ?? 60);
$currentBatch = (int)($settings['item_sync_batch'] ?? 100);
$enabled = settings_bool('item_sync_enabled', false);
$singleKeyword = site_setting_get('item_sync_keyword', '');
$compoundLines = preg_split('/\R/u', site_setting_get('item_sync_compound_keywords', '')) ?: [];
$excludeLines = preg_split('/\R/u', site_setting_get('item_sync_exclude_keywords', '')) ?: [];

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>自動設定</h1>
  <?php if ($message !== ''): ?>
    <div class="admin-notice <?= $messageType === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>">
      <p><?= e($message) ?></p>
    </div>
  <?php endif; ?>

  <form method="post" class="stack" style="max-width:900px;">
    <?= csrf_input() ?>
    <label><input type="checkbox" name="item_sync_enabled" value="1" <?= $enabled ? 'checked' : '' ?>> 自動更新を有効化</label>

    <label>自動更新間隔（分）
      <select name="item_sync_interval_minutes">
        <?php foreach ($intervalOptions as $value): ?>
          <option value="<?= e((string)$value) ?>" <?= $currentInterval === $value ? 'selected' : '' ?>><?= e((string)$value) ?>分</option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>1回の自動更新で取得する記事数
      <select name="item_sync_batch">
        <?php foreach ($batchOptions as $value): ?>
          <option value="<?= e((string)$value) ?>" <?= $currentBatch === $value ? 'selected' : '' ?>><?= e((string)$value) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>取得キーワード（単体）
      <input type="text" name="item_sync_keyword" value="<?= e($singleKeyword) ?>" placeholder="通常のkeyword">
    </label>

    <h2>複合キーワード（最大5）</h2>
    <p>1行に「A,B」の形式で入力します。APIには「BはAが大好き」として keyword に渡します。</p>
    <?php for ($i = 1; $i <= 5; $i++): ?>
      <label>複合キーワード<?= e((string)$i) ?>
        <input type="text" name="item_sync_compound_<?= e((string)$i) ?>" value="<?= e((string)($compoundLines[$i - 1] ?? '')) ?>" placeholder="A,B">
      </label>
    <?php endfor; ?>

    <h2>拒否（禁止）キーワード（最大5）</h2>
    <p>タイトル部分一致で除外します（表示/投稿どちらにも適用）。</p>
    <?php for ($i = 1; $i <= 5; $i++): ?>
      <label>除外キーワード<?= e((string)$i) ?>
        <input type="text" name="item_sync_exclude_<?= e((string)$i) ?>" value="<?= e((string)($excludeLines[$i - 1] ?? '')) ?>">
      </label>
    <?php endfor; ?>

    <div class="admin-actions"><button type="submit">保存</button></div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
