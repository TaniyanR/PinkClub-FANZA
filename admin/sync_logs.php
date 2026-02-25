<?php

declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title='同期ログ';
$logs=db()->query('SELECT * FROM sync_logs ORDER BY id DESC LIMIT 200')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<h2>同期ログ一覧</h2>
<table><tr><th>ID</th><th>種別</th><th>成否</th><th>件数</th><th>メッセージ</th><th>時刻</th></tr><?php foreach($logs as $log):?><tr><td><?=e($log['id'])?></td><td><?=e($log['sync_type'])?></td><td><?=$log['is_success']?'OK':'NG'?></td><td><?=e($log['synced_count'])?></td><td><?=e($log['message'])?></td><td><?=e($log['created_at'])?></td></tr><?php endforeach;?></table>
<?php require __DIR__ . '/includes/footer.php'; ?>
