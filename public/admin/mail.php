<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:mail.php');
require_once __DIR__ . '/../../lib/site_settings.php';

function admin_mail_log(string $to, string $subject, string $body, bool $ok, ?string $error): void
{
    db()->prepare('INSERT INTO mail_logs(direction,from_name,from_email,to_email,subject,body,status,last_error,created_at,updated_at) VALUES ("out",NULL,:from_email,:to_email,:subject,:body,:status,:error,NOW(),NOW())')
        ->execute([
            ':from_email' => (string)site_setting_get('mail.from_email', 'noreply@pinkclub.local'),
            ':to_email' => $to,
            ':subject' => $subject,
            ':body' => $body,
            ':status' => $ok ? 'sent' : 'failed',
            ':error' => $ok ? null : ($error ?? 'mail() unavailable'),
        ]);
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_notify') {
            $notify = trim((string)($_POST['notify_email'] ?? ''));
            $from = trim((string)($_POST['from_email'] ?? ''));
            if ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
                $error = '通知先メールアドレスが不正です。';
            } elseif ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $error = '送信元メールアドレスが不正です。';
            } else {
                site_setting_set_many([
                    'mail.notify_email' => $notify,
                    'mail.from_email' => $from,
                ]);
                admin_flash_set('ok', 'メール設定を保存しました。');
                header('Location: ' . admin_url('mail.php'));
                exit;
            }
        } elseif ($action === 'send_test') {
            $to = trim((string)($_POST['test_to_email'] ?? site_setting_get('mail.notify_email', '')));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $error = 'テスト送信先メールアドレスが不正です。';
            } else {
                $subject = '[PinkClub-FANZA] メール送信テスト';
                $body = "これは管理画面からのテストメールです。\n" . date('Y-m-d H:i:s');
                $headers = [];
                $from = trim(site_setting_get('mail.from_email', ''));
                if ($from !== '') {
                    $headers[] = 'From: ' . $from;
                }
                $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
                $lastError = error_get_last();
                admin_mail_log($to, $subject, $body, $ok, $ok ? null : (($lastError['message'] ?? 'mail() failed')));
                if ($ok) {
                    admin_flash_set('ok', 'テストメールを送信しました。');
                } else {
                    admin_flash_set('ng', 'テストメール送信に失敗しました。mail_logsを確認してください。');
                }
                header('Location: ' . admin_url('mail.php'));
                exit;
            }
        }
    }
}

$detailId = (int)($_GET['id'] ?? 0);
$detail = null;
if ($detailId > 0) {
    $stmt = db()->prepare('SELECT * FROM mail_logs WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $detailId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $detail = is_array($row) ? $row : null;
}

$logs = db()->query('SELECT id, created_at, subject, from_email, to_email, status, last_error FROM mail_logs ORDER BY created_at DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$notifyEmail = site_setting_get('mail.notify_email', '');
$fromEmail = site_setting_get('mail.from_email', '');
$okMessage = admin_flash_get('ok');
$ngMessage = admin_flash_get('ng');

$pageTitle = 'メール';
ob_start();
?>
<h1>メール</h1>
<?php if ($okMessage !== '') : ?><div class="admin-card"><p><?php echo e($okMessage); ?></p></div><?php endif; ?>
<?php if ($ngMessage !== '') : ?><div class="admin-card"><p style="color:#d63638"><?php echo e($ngMessage); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p style="color:#d63638"><?php echo e($error); ?></p></div><?php endif; ?>

<div class="admin-card">
    <h2>通知先設定</h2>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="save_notify">
        <label>通知先メールアドレス</label>
        <input type="email" name="notify_email" value="<?php echo e($notifyEmail); ?>" placeholder="admin@example.com">
        <label>送信元メールアドレス（任意）</label>
        <input type="email" name="from_email" value="<?php echo e($fromEmail); ?>" placeholder="noreply@example.com">
        <button type="submit">保存</button>
    </form>
</div>

<div class="admin-card">
    <h2>テスト送信</h2>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="send_test">
        <label>送信先</label>
        <input type="email" name="test_to_email" value="<?php echo e($notifyEmail); ?>" required>
        <button type="submit">テスト送信</button>
    </form>
</div>

<?php if ($detail !== null) : ?>
    <div class="admin-card">
        <h2>メール詳細 #<?php echo e((string)$detail['id']); ?></h2>
        <p><strong>件名:</strong> <?php echo e((string)$detail['subject']); ?></p>
        <p><strong>送信元:</strong> <?php echo e((string)($detail['from_email'] ?? '')); ?></p>
        <p><strong>送信先:</strong> <?php echo e((string)($detail['to_email'] ?? '')); ?></p>
        <p><strong>ステータス:</strong> <?php echo e((string)$detail['status']); ?></p>
        <p><strong>日時:</strong> <?php echo e((string)$detail['created_at']); ?></p>
        <p><strong>本文:</strong><br><?php echo nl2br(e((string)$detail['body'])); ?></p>
    </div>
<?php endif; ?>

<div class="admin-card">
    <h2>送信ログ（最新100件）</h2>
    <table class="admin-table">
        <thead>
            <tr><th>日時</th><th>件名</th><th>送信元</th><th>送信先</th><th>status</th><th>last_error</th></tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log) : $lastError = trim((string)($log['last_error'] ?? '')); ?>
                <tr>
                    <td><?php echo e((string)$log['created_at']); ?></td>
                    <td><a href="<?php echo e(admin_url('mail.php?id=' . (string)$log['id'])); ?>"><?php echo e((string)$log['subject']); ?></a></td>
                    <td><?php echo e((string)($log['from_email'] ?? '')); ?></td>
                    <td><?php echo e((string)($log['to_email'] ?? '')); ?></td>
                    <td><?php echo e((string)$log['status']); ?></td>
                    <td style="color:<?php echo $lastError !== '' ? '#d63638' : 'inherit'; ?>"><?php echo e($lastError !== '' ? mb_strimwidth($lastError, 0, 80, '...') : ''); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($logs === []) : ?><tr><td colspan="6">メールログはまだありません。</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
