<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';
require_once __DIR__ . '/../../lib/scheduler.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_post_csrf_valid()) {
    $interval = scheduler_normalize_interval((int)($_POST['interval_minutes'] ?? 60));
    $maxItems = scheduler_normalize_item_limit((int)($_POST['max_items'] ?? 100));

    $state = scheduler_get_state(db());
    db()->prepare('UPDATE api_schedules SET interval_minutes=:m, updated_at=NOW() WHERE id=:id')
        ->execute([':m' => $interval, ':id' => (int)$state['id']]);
    site_setting_set('api_item_limit', (string)$maxItems);

    admin_flash_set('ok', 'スケジュール設定を保存しました。');
    header('Location: ' . admin_url('api_logs.php'));
    exit;
}

$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($pageNum - 1) * $perPage;
$stmt = db()->prepare('SELECT * FROM api_logs ORDER BY created_at DESC LIMIT :l OFFSET :o');
$stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':o', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = (int)(db()->query('SELECT COUNT(*) FROM api_logs')->fetchColumn() ?: 0);
$totalPages = max(1, (int)ceil($total / $perPage));
$sch = scheduler_get_state(db());
$itemLimit = scheduler_normalize_item_limit((int)site_setting_get('api_item_limit', '100'));
$ok = admin_flash_get('ok');

$pageTitle = 'API履歴';
ob_start();
?>
<h1>API履歴 / 内部タイマー</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <label>実行間隔</label>
        <select name="interval_minutes"><?php foreach (scheduler_allowed_intervals() as $m) : ?><option value="<?php echo e((string)$m); ?>" <?php echo ((int)$sch['interval_minutes'] === $m) ? 'selected' : ''; ?>><?php echo e((string)$m); ?>分</option><?php endforeach; ?></select>
        <label>取得件数</label>
        <select name="max_items"><?php foreach (scheduler_allowed_item_limits() as $n) : ?><option value="<?php echo e((string)$n); ?>" <?php echo ($itemLimit === $n) ? 'selected' : ''; ?>><?php echo e((string)$n); ?></option><?php endforeach; ?></select>
        <button>保存</button>
    </form>
    <p>fail_count: <?php echo e((string)($sch['fail_count'] ?? 0)); ?> / last_error: <?php echo e((string)($sch['last_error'] ?? '')); ?></p>
</div>
<div class="admin-card"><table class="admin-table"><thead><tr><th>日時</th><th>endpoint</th><th>status</th><th>件数</th><th>error</th></tr></thead><tbody><?php foreach ($logs as $log) : ?><tr><td><?php echo e((string)$log['created_at']); ?></td><td><?php echo e((string)$log['endpoint']); ?></td><td><?php echo e((string)$log['status']); ?></td><td><?php echo e((string)$log['item_count']); ?></td><td><?php echo e((string)($log['error_message'] ?? '')); ?></td></tr><?php endforeach; ?><?php if ($logs === []) : ?><tr><td colspan="5">履歴がありません。</td></tr><?php endif; ?></tbody></table></div>
<?php if ($totalPages > 1) : ?><div class="admin-card"><?php for ($i = 1; $i <= $totalPages; $i++) : ?><a href="<?php echo e(admin_url('api_logs.php?page=' . (string)$i)); ?>"><?php echo e((string)$i); ?></a> <?php endfor; ?></div><?php endif; ?>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
