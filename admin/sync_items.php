<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_validate_or_fail(post('_csrf'));
    try {
        $count=dmm_sync_service()->syncItems((string)post('service_code','digital'),(string)post('floor_code','videoa'));
        flash_set('success',"商品同期: {$count}件");
    } catch(Throwable $e){flash_set('error','商品同期失敗: '.$e->getMessage());}
    app_redirect('admin/sync_items.php');
}
$title='商品同期';
$logs=db()->query("SELECT * FROM sync_logs WHERE sync_type='item' ORDER BY id DESC LIMIT 30")->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<h2>商品同期</h2>
<form method="post"><?= csrf_input() ?>
<label>service <input name="service_code" value="digital"></label>
<label>floor <input name="floor_code" value="videoa"></label>
<button type="submit">同期</button>
</form>
<table><tr><th>時刻</th><th>結果</th><th>件数</th><th>メッセージ</th></tr><?php foreach($logs as $l):?><tr><td><?=e($l['created_at'])?></td><td><?=$l['is_success']?'OK':'NG'?></td><td><?=e($l['synced_count'])?></td><td><?=e($l['message'])?></td></tr><?php endforeach;?></table>
<?php require __DIR__ . '/includes/footer.php'; ?>
