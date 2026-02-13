<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_post_csrf_valid()) {
    $interval = (int)($_POST['interval_hours'] ?? 1);
    $maxItems = (int)($_POST['max_items'] ?? 100);
    $interval = in_array($interval, [1,3,6,12,24], true) ? $interval : 1;
    $maxItems = in_array($maxItems, [10,100,500,1000], true) ? $maxItems : 100;
    db()->prepare('INSERT INTO api_schedules(schedule_type,last_run_at,next_run_at,interval_minutes,interval_hours,max_items,lock_until,is_enabled,fail_count,last_error,created_at,updated_at) VALUES ("item_import",NULL,NULL,:m,:h,:max,NULL,1,0,NULL,NOW(),NOW()) ON DUPLICATE KEY UPDATE interval_minutes=VALUES(interval_minutes),interval_hours=VALUES(interval_hours),max_items=VALUES(max_items),updated_at=NOW()')
        ->execute([':m' => $interval * 60, ':h' => $interval, ':max' => $maxItems]);
    admin_flash_set('ok', 'スケジュール設定を保存しました。');
    header('Location: ' . admin_url('api_logs.php'));exit;
}

$pageNum=max(1,(int)($_GET['page']??1));$perPage=50;$offset=($pageNum-1)*$perPage;
$stmt=db()->prepare('SELECT * FROM api_logs ORDER BY created_at DESC LIMIT :l OFFSET :o');$stmt->bindValue(':l',$perPage,PDO::PARAM_INT);$stmt->bindValue(':o',$offset,PDO::PARAM_INT);$stmt->execute();$logs=$stmt->fetchAll(PDO::FETCH_ASSOC);
$total=(int)(db()->query('SELECT COUNT(*) FROM api_logs')->fetchColumn()?:0);$totalPages=max(1,(int)ceil($total/$perPage));
$sch=db()->query("SELECT * FROM api_schedules WHERE schedule_type='item_import' LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: ['interval_hours'=>1,'max_items'=>100,'fail_count'=>0,'last_error'=>''];
$ok=admin_flash_get('ok');

$pageTitle='API履歴';ob_start(); ?>
<h1>API履歴 / 内部タイマー</h1>
<?php if($ok!==''): ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<div class="admin-card"><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><label>実行間隔</label><select name="interval_hours"><?php foreach([1,3,6,12,24] as $h): ?><option value="<?php echo e((string)$h); ?>" <?php echo ((int)$sch['interval_hours']===$h)?'selected':''; ?>><?php echo e((string)$h); ?>時間</option><?php endforeach; ?></select><label>取得件数</label><select name="max_items"><?php foreach([10,100,500,1000] as $n): ?><option value="<?php echo e((string)$n); ?>" <?php echo ((int)$sch['max_items']===$n)?'selected':''; ?>><?php echo e((string)$n); ?></option><?php endforeach; ?></select><button>保存</button></form><p>fail_count: <?php echo e((string)$sch['fail_count']); ?> / last_error: <?php echo e((string)($sch['last_error'] ?? '')); ?></p></div>
<div class="admin-card"><table class="admin-table"><thead><tr><th>日時</th><th>endpoint</th><th>status</th><th>件数</th><th>error</th></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?php echo e((string)$log['created_at']); ?></td><td><?php echo e((string)$log['endpoint']); ?></td><td><?php echo e((string)$log['status']); ?></td><td><?php echo e((string)$log['item_count']); ?></td><td><?php echo e((string)($log['error_message']??'')); ?></td></tr><?php endforeach; ?><?php if($logs===[]): ?><tr><td colspan="5">履歴がありません。</td></tr><?php endif; ?></tbody></table></div>
<?php if($totalPages>1): ?><div class="admin-card"><?php for($i=1;$i<=$totalPages;$i++): ?><a href="<?php echo e(admin_url('api_logs.php?page='.(string)$i)); ?>"><?php echo e((string)$i); ?></a> <?php endfor; ?></div><?php endif; ?>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
