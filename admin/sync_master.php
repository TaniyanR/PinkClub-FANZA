<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$types=['actress'=>'女優','genre'=>'ジャンル','maker'=>'メーカー','series'=>'シリーズ','author'=>'作者'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_validate_or_fail(post('_csrf'));
    $type=(string)post('type');
    $floorId=trim((string)post('floor_id','')) ?: null;
    try {
        $count=dmm_sync_service()->syncMaster($type,$floorId);
        flash_set('success',"{$types[$type]}同期: {$count}件");
    } catch(Throwable $e){flash_set('error','マスタ同期失敗: '.$e->getMessage());}
    app_redirect('admin/sync_master.php');
}
$title='マスタ同期';
require __DIR__ . '/includes/header.php';
?>
<h2>マスタ同期</h2>
<?php foreach($types as $key=>$label): ?>
<form method="post">
<?= csrf_input() ?>
<input type="hidden" name="type" value="<?=e($key)?>">
<label><?=e($label)?> floor_id(任意): <input name="floor_id"></label>
<button type="submit">同期</button>
</form>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
