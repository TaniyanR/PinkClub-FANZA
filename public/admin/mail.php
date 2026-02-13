<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$currentUserId = admin_current_user_id();
$notify = '';
if ($currentUserId !== null) {
    $stmt = db()->prepare('SELECT email FROM admin_users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $currentUserId]);
    $notify = (string)($stmt->fetchColumn() ?: '');
}

$logs = db()->query('SELECT * FROM mail_logs ORDER BY created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'メール';
ob_start();
?>
<h1>メールログ</h1>
<div class="admin-card"><p>通知先メール: <?php echo e($notify !== '' ? $notify : '未設定'); ?></p></div>
<div class="admin-card"><table class="admin-table"><thead><tr><th>日時</th><th>件名</th><th>送信元</th><th>結果</th></tr></thead><tbody>
<?php foreach ($logs as $log) : ?><tr><td><?php echo e((string)$log['created_at']); ?></td><td><?php echo e((string)$log['subject']); ?></td><td><?php echo e((string)$log['from_email']); ?></td><td><?php echo (int)$log['sent_ok'] === 1 ? 'OK' : '失敗'; ?></td></tr><?php endforeach; ?>
<?php if ($logs === []) : ?><tr><td colspan="4">メールログはありません。</td></tr><?php endif; ?>
</tbody></table></div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
