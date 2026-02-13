<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $to = trim((string)($_POST['notification_to'] ?? ''));
        db()->prepare('INSERT INTO site_settings(setting_key, setting_value, updated_at, created_at) VALUES ("mail.notification_to", :v, NOW(), NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()')
            ->execute([':v' => $to]);
        admin_flash_set('ok', '通知先を保存しました。');
        header('Location: ' . admin_url('mail.php'));
        exit;
    }
}

$notify = (string)(db()->query("SELECT setting_value FROM site_settings WHERE setting_key='mail.notification_to' LIMIT 1")->fetchColumn() ?: '');
$logs = db()->query('SELECT * FROM mail_logs ORDER BY created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$ok = admin_flash_get('ok');
$pageTitle = 'メール';
ob_start();
?>
<h1>メール</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
<form method="post">
<input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
<label>通知先メールアドレス</label>
<input type="email" name="notification_to" value="<?php echo e($notify); ?>" placeholder="admin@example.com">
<button type="submit">保存</button>
</form>
</div>
<div class="admin-card"><table class="admin-table"><thead><tr><th>日時</th><th>From</th><th>件名</th><th>結果</th></tr></thead><tbody>
<?php foreach ($logs as $log) : ?><tr><td><?php echo e((string)$log['created_at']); ?></td><td><?php echo e((string)$log['from_email']); ?></td><td><?php echo e((string)$log['subject']); ?></td><td><?php echo ((int)$log['sent_ok'] === 1) ? '送信成功' : 'ログ保存'; ?></td></tr><?php endforeach; ?>
<?php if ($logs === []) : ?><tr><td colspan="4">メールログはありません。</td></tr><?php endif; ?>
</tbody></table></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
