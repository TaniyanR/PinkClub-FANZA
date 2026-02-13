<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_session_start();
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        $message = 'リクエストが無効です。';
    } else {
        $identity = trim((string)($_POST['identity'] ?? ''));
        $stmt = db()->prepare('SELECT id, username FROM admin_users WHERE (username=:v OR username=:u) AND is_active=1 LIMIT 1');
        $stmt->execute([':v' => $identity, ':u' => $identity]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($u)) {
            $token = bin2hex(random_bytes(24));
            db()->prepare('INSERT INTO admin_password_resets(admin_user_id, token_hash, expires_at, used_at, created_at) VALUES (:uid,:h,DATE_ADD(NOW(), INTERVAL 60 MINUTE),NULL,NOW())')
                ->execute([':uid' => (int)$u['id'], ':h' => hash('sha256', $token)]);
            $link = base_url() . '/reset_password.php?token=' . rawurlencode($token);
            $body = "管理者パスワード再設定リンク\n" . $link;
            $to = (string)(config_get('mail.to', 'admin@example.com'));
            $ok = @mail($to, '[PinkClub-FANZA] Password Reset', $body);
            db()->prepare('INSERT INTO mail_logs(direction,from_name,from_email,to_email,subject,body,status,last_error,created_at,updated_at) VALUES ("out",NULL,:from,:to,:subj,:body,:status,:err,NOW(),NOW())')
                ->execute([':from' => 'noreply@pinkclub.local', ':to' => $to, ':subj' => 'Password Reset', ':body' => $body, ':status' => $ok ? 'sent' : 'failed', ':err' => $ok ? null : 'mail() unavailable']);
        }
        $message = '入力情報を受け付けました。該当ユーザーが存在する場合は再設定案内を送信しました。';
    }
}

$pageTitle = 'パスワード再発行';
include __DIR__ . '/partials/login_header.php';
?>
<div class="login-page">
    <div class="login-headline"><span class="login-headline__item">PinkClub-FANZA</span><span class="login-headline__item">パスワード再発行</span></div>
    <section class="admin-card login-card">
        <?php if ($message !== '') : ?><p><?php echo e($message); ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <label>ユーザー名</label>
            <input name="identity" required>
            <button type="submit">再発行メールを送る</button>
        </form>
        <div class="login-help"><a href="<?php echo e(login_url()); ?>">ログインへ戻る</a></div>
    </section>
</div>
<?php include __DIR__ . '/partials/login_footer.php';
