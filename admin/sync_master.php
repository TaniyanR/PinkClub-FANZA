<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();

$types = ['actress' => '女優', 'genre' => 'ジャンル', 'maker' => 'メーカー', 'series' => 'シリーズ', 'author' => '作者'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail(post('_csrf'));
    $type = (string) post('type');
    $floorId = trim((string) post('floor_id', '')) ?: null;
    try {
        $count = dmm_sync_service()->syncMaster($type, $floorId);
        flash_set('success', "{$types[$type]}同期: {$count}件");
    } catch (Throwable $e) {
        flash_set('error', 'マスタ同期失敗: ' . $e->getMessage());
    }
    app_redirect('admin/sync_master.php');
}

$title = 'Masters';
require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>Masters</h1>
  <p class="admin-form-note">必要なマスタのみ都度同期できます。</p>
</section>

<?php foreach ($types as $key => $label): ?>
  <form method="post" class="admin-card">
    <?= csrf_input() ?>
    <input type="hidden" name="type" value="<?= e($key) ?>">
    <label><?= e($label) ?> floor_id(任意)
      <input name="floor_id">
    </label>
    <button class="button-secondary" type="submit">同期</button>
  </form>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
