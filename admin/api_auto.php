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
        'item_sync_compound_keywords' => implode("\n", $compoundKeywords),
        'item_sync_exclude_keywords' => implode("\n", $excludeKeywords),
    ]);

    $message = '自動設定を保存しました。';
}

$settings = settings_get();
$currentInterval = (int)($settings['item_sync_interval_minutes'] ?? 60);
$currentBatch = (int)($settings['item_sync_batch'] ?? 100);
$enabled = settings_bool('item_sync_enabled', false);
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

  <form method="post" class="stack" style="max-width:980px;">
    <?= csrf_input() ?>

    <div style="display:grid;grid-template-columns:190px minmax(280px,1fr);gap:12px 16px;align-items:center;">
      <div><strong>自動更新を有効化</strong></div>
      <div><label><input type="checkbox" name="item_sync_enabled" value="1" <?= $enabled ? 'checked' : '' ?>></label></div>

      <div><strong>自動更新間隔（分）</strong></div>
      <div>
        <select name="item_sync_interval_minutes" style="width:100%;">
          <?php foreach ($intervalOptions as $value): ?>
            <option value="<?= e((string)$value) ?>" <?= $currentInterval === $value ? 'selected' : '' ?>><?= e((string)$value) ?>分</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div><strong>取得する記事数</strong></div>
      <div>
        <select name="item_sync_batch" style="width:100%;">
          <?php foreach ($batchOptions as $value): ?>
            <option value="<?= e((string)$value) ?>" <?= $currentBatch === $value ? 'selected' : '' ?>><?= e((string)$value) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h2 style="margin-top:20px;">複合キーワード（最大5）</h2>
    <p>単体キーワード（例: 学園）または複合（例: A,B）を入力できます。複合はAPIに「BはAが大好き」として渡します。</p>

    <div style="display:grid;grid-template-columns:190px minmax(280px,1fr);gap:12px 16px;align-items:center;">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <div><strong>キーワード<?= e((string)$i) ?></strong></div>
        <div><input type="text" name="item_sync_compound_<?= e((string)$i) ?>" value="<?= e((string)($compoundLines[$i - 1] ?? '')) ?>" placeholder="A,B" style="width:100%;"></div>
      <?php endfor; ?>
    </div>

    <h2 style="margin-top:20px;">拒否（禁止）キーワード（最大5）</h2>
    <p>タイトル部分一致で除外します（表示/投稿どちらにも適用）。</p>

    <div style="display:grid;grid-template-columns:190px minmax(280px,1fr);gap:12px 16px;align-items:center;">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <div><strong>除外キーワード<?= e((string)$i) ?></strong></div>
        <div><input type="text" name="item_sync_exclude_<?= e((string)$i) ?>" value="<?= e((string)($excludeLines[$i - 1] ?? '')) ?>" style="width:100%;"></div>
      <?php endfor; ?>
    </div>

    <div class="admin-actions" style="margin-top:20px;"><button type="submit">保存</button></div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
