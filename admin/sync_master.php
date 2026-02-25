<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/dmm_sync_service.php';
require_admin();

$result = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $type = (string)($_POST['sync_type'] ?? 'actress');
        $svc = new DmmSyncService();
        $params = [
            'site' => (string)($_POST['site'] ?? get_setting('default_site','FANZA')),
            'service' => (string)($_POST['service'] ?? get_setting('default_service','digital')),
            'floor' => (string)($_POST['floor'] ?? get_setting('default_floor','videoa')),
            'initial' => (string)($_POST['initial'] ?? ''),
            'keyword' => (string)($_POST['keyword'] ?? ''),
            'hits' => (int)($_POST['hits'] ?? 100),
        ];
        $map = ['actress'=>'syncActresses','genre'=>'syncGenres','maker'=>'syncMakers','series'=>'syncSeries','author'=>'syncAuthors'];
        $result = $svc->{$map[$type]}($params);
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
include __DIR__ . '/includes/header.php';
?>
<div class="card"><h1>マスタ同期</h1>
<?php if ($result): ?><div class="alert success"><?= e(json_encode($result, JSON_UNESCAPED_UNICODE)) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_input() ?>
<label>同期対象<select name="sync_type"><option value="actress">女優</option><option value="genre">ジャンル</option><option value="maker">メーカー</option><option value="series">シリーズ</option><option value="author">作者</option></select></label>
<label>site<input name="site" value="<?= e(get_setting('default_site','FANZA')) ?>"></label>
<label>service<input name="service" value="<?= e(get_setting('default_service','digital')) ?>"></label>
<label>floor<input name="floor" value="<?= e(get_setting('default_floor','videoa')) ?>"></label>
<label>initial<input name="initial" placeholder="あ/か/A 等"></label>
<label>keyword(女優のみ)<input name="keyword"></label>
<label>hits<input type="number" name="hits" value="100"></label>
<button type="submit">同期実行</button>
</form></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
