<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $interval = (int)($_POST['interval_minutes'] ?? 60);
        if (!in_array($interval, [10, 30, 60, 120], true)) {
            $interval = 60;
        }

        db()->prepare('INSERT INTO api_schedules(schedule_type,interval_minutes,last_run_at,lock_until,fail_count,last_error,is_enabled,created_at,updated_at) VALUES("rss_fetch",:interval,NULL,NULL,0,NULL,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE interval_minutes=VALUES(interval_minutes),updated_at=NOW()')
            ->execute([':interval' => $interval]);

        admin_flash_set('ok', 'API設定を保存しました。');
        header('Location: ' . admin_url('api_settings.php'));
        exit;
    }
}

$schedule = db()->query("SELECT * FROM api_schedules WHERE schedule_type='rss_fetch' LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: ['interval_minutes' => 60, 'fail_count' => 0, 'last_error' => null, 'last_run_at' => null];
$ok = admin_flash_get('ok');
$pageTitle = 'API設定';
ob_start();
?>
<h1>API設定</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<?php if ((int)($schedule['fail_count'] ?? 0) >= 5) : ?><div class="admin-card"><p>連続失敗が5回以上です。<?php echo e((string)($schedule['last_error'] ?? '')); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <label>実行間隔（分）</label>
        <select name="interval_minutes">
            <?php foreach ([10,30,60,120] as $m) : ?><option value="<?php echo e((string)$m); ?>" <?php echo (int)$schedule['interval_minutes'] === $m ? 'selected' : ''; ?>><?php echo e((string)$m); ?></option><?php endforeach; ?>
        </select>
        <p>最終実行: <?php echo e((string)($schedule['last_run_at'] ?? '-')); ?></p>
        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
