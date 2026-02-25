<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';
require_admin();

$keys = ['dmm_api_id','dmm_affiliate_id','default_site','default_service','default_floor','sync_hits_default'];
$settings = [];
foreach ($keys as $k) $settings[$k] = get_setting($k, $k==='default_site'?'FANZA':($k==='default_service'?'digital':($k==='default_floor'?'videoa':'')));
$result = $_SESSION['sync_result'] ?? null; unset($_SESSION['sync_result']);
$error = $_SESSION['sync_error'] ?? null; unset($_SESSION['sync_error']);

include __DIR__ . '/includes/header.php';
?>
<div class="card"><h1>API設定</h1>
<?php if ($result): ?><div class="alert success"><?= e(json_encode($result, JSON_UNESCAPED_UNICODE)) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="/admin/save_settings.php"><?= csrf_input() ?>
<label>API ID<input name="dmm_api_id" value="<?= e($settings['dmm_api_id']) ?>"></label>
<label>Affiliate ID<input name="dmm_affiliate_id" value="<?= e($settings['dmm_affiliate_id']) ?>"></label>
<label>Default Site<input name="default_site" value="<?= e($settings['default_site']) ?>"></label>
<label>Default Service<input name="default_service" value="<?= e($settings['default_service']) ?>"></label>
<label>Default Floor<input name="default_floor" value="<?= e($settings['default_floor']) ?>"></label>
<label>Sync hits<input type="number" name="sync_hits_default" value="<?= e($settings['sync_hits_default']) ?>"></label>
<div style="margin-top:12px">
<button name="save" value="1">設定保存</button>
<button name="test_connection" value="1" class="secondary">接続テスト</button>
<button name="sync_execute" value="1">同期実行</button>
<button name="sync_floor" value="1" class="secondary">Floor同期</button>
<a href="/admin/sync_master.php" class="btn">各マスタ同期へ</a>
</div>
</form></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
