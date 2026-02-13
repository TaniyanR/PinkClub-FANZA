<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$detailId = (int)($_GET['id'] ?? 0);
$detail = null;
if ($detailId > 0) {
    $stmt = db()->prepare('SELECT * FROM mail_logs WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $detailId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $detail = is_array($row) ? $row : null;
}

$logs = db()->query('SELECT id, created_at, subject, from_email, status, last_error FROM mail_logs ORDER BY created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'メール';
ob_start();
?>
<h1>メール</h1>

<?php if ($detail !== null) : ?>
    <div class="admin-card">
        <h2>メール詳細 #<?php echo e((string)$detail['id']); ?></h2>
        <p><strong>件名:</strong> <?php echo e((string)$detail['subject']); ?></p>
        <p><strong>送信者名:</strong> <?php echo e((string)($detail['from_name'] ?? '')); ?></p>
        <p><strong>送信元:</strong> <?php echo e((string)($detail['from_email'] ?? '')); ?></p>
        <p><strong>送信先:</strong> <?php echo e((string)($detail['to_email'] ?? '')); ?></p>
        <p><strong>ステータス:</strong> <?php echo e((string)$detail['status']); ?></p>
        <p><strong>日時:</strong> <?php echo e((string)$detail['created_at']); ?></p>
        <p><strong>本文:</strong><br><?php echo nl2br(e((string)$detail['body'])); ?></p>
    </div>
<?php endif; ?>

<?php if ($logs === []) : ?>
    <div class="admin-card"><p>メールログはまだありません。</p></div>
<?php else : ?>
    <div class="admin-card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>日時</th>
                    <th>件名</th>
                    <th>from_email</th>
                    <th>status</th>
                    <th>last_error</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <?php $lastError = trim((string)($log['last_error'] ?? '')); ?>
                    <tr>
                        <td><?php echo e((string)$log['created_at']); ?></td>
                        <td><a href="<?php echo e(admin_url('mail.php?id=' . (string)$log['id'])); ?>"><?php echo e((string)$log['subject']); ?></a></td>
                        <td><?php echo e((string)($log['from_email'] ?? '')); ?></td>
                        <td><?php echo e((string)$log['status']); ?></td>
                        <td><?php echo e($lastError !== '' ? mb_strimwidth($lastError, 0, 80, '...') : ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
