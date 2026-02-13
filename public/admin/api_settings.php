<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $pairs = [
            'api.api_id' => trim((string)($_POST['api_id'] ?? '')),
            'api.affiliate_id' => trim((string)($_POST['affiliate_id'] ?? '')),
            'api.site' => trim((string)($_POST['site'] ?? 'FANZA')),
            'api.service' => trim((string)($_POST['service'] ?? 'digital')),
            'api.floor' => trim((string)($_POST['floor'] ?? 'videoa')),
        ];
        foreach ($pairs as $key => $value) {
            db()->prepare('INSERT INTO site_settings(setting_key,setting_value,updated_at,created_at) VALUES (:k,:v,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()')
                ->execute([':k' => $key, ':v' => $value]);
        }

        $stmt = db()->prepare('INSERT INTO api_schedules(schedule_type,interval_hours,max_items,lock_until,last_run_at,fail_count,is_enabled,created_at,updated_at) VALUES ("item_import",:interval_hours,:max_items,:lock_until,:last_run_at,:fail_count,:is_enabled,NOW(),NOW()) ON DUPLICATE KEY UPDATE interval_hours=VALUES(interval_hours), max_items=VALUES(max_items), lock_until=VALUES(lock_until), last_run_at=VALUES(last_run_at), fail_count=VALUES(fail_count), is_enabled=VALUES(is_enabled), updated_at=NOW()');
        $stmt->execute([
            ':interval_hours' => max(1, (int)($_POST['interval_hours'] ?? 1)),
            ':max_items' => max(1, (int)($_POST['max_items'] ?? 100)),
            ':lock_until' => trim((string)($_POST['lock_until'] ?? '')) ?: null,
            ':last_run_at' => trim((string)($_POST['last_run_at'] ?? '')) ?: null,
            ':fail_count' => max(0, (int)($_POST['fail_count'] ?? 0)),
            ':is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
        ]);

        admin_flash_set('ok', 'API設定を保存しました。');
        header('Location: ' . admin_url('api_settings.php'));
        exit;
    }
}

$settings = db()->query("SELECT setting_key,setting_value FROM site_settings WHERE setting_key LIKE 'api.%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$scheduleStmt = db()->query("SELECT * FROM api_schedules WHERE schedule_type='item_import' LIMIT 1");
$schedule = $scheduleStmt ? ($scheduleStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
$ok = admin_flash_get('ok');

$pageTitle = 'API設定';
ob_start();
?>
<h1>API設定</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <label>API ID</label>
        <input type="text" name="api_id" value="<?php echo e((string)($settings['api.api_id'] ?? '')); ?>">
        <label>Affiliate ID</label>
        <input type="text" name="affiliate_id" value="<?php echo e((string)($settings['api.affiliate_id'] ?? '')); ?>">
        <label>Site</label>
        <input type="text" name="site" value="<?php echo e((string)($settings['api.site'] ?? 'FANZA')); ?>">
        <label>Service</label>
        <input type="text" name="service" value="<?php echo e((string)($settings['api.service'] ?? 'digital')); ?>">
        <label>Floor</label>
        <input type="text" name="floor" value="<?php echo e((string)($settings['api.floor'] ?? 'videoa')); ?>">

        <h2>取得スケジュール</h2>
        <label>interval (時間)</label>
        <input type="number" name="interval_hours" min="1" value="<?php echo e((string)($schedule['interval_hours'] ?? 1)); ?>">
        <label>件数上限</label>
        <input type="number" name="max_items" min="1" value="<?php echo e((string)($schedule['max_items'] ?? 100)); ?>">
        <label>lock_until (YYYY-mm-dd HH:ii:ss)</label>
        <input type="text" name="lock_until" value="<?php echo e((string)($schedule['lock_until'] ?? '')); ?>">
        <label>last_run (YYYY-mm-dd HH:ii:ss)</label>
        <input type="text" name="last_run_at" value="<?php echo e((string)($schedule['last_run_at'] ?? '')); ?>">
        <label>fail_count</label>
        <input type="number" name="fail_count" min="0" value="<?php echo e((string)($schedule['fail_count'] ?? 0)); ?>">
        <label><input type="checkbox" name="is_enabled" value="1" <?php echo ((int)($schedule['is_enabled'] ?? 1) === 1) ? 'checked' : ''; ?>> スケジュール有効</label>

        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
