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
        $email = trim((string)($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'メールアドレスの形式が正しくありません。';
        } else {
            $stmt = db()->prepare('SELECT id, username, email FROM admin_users WHERE email=:email AND is_active=1 LIMIT 1');
            $stmt->execute([':email' => $email]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($u)) {
                $newPassword = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12);
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                db()->prepare('UPDATE admin_users SET password_hash=:hash, password=NULL, updated_at=NOW() WHERE id=:id')
                    ->execute([':hash' => $hash, ':id' => (int)$u['id']]);

                $body = "管理者パスワードを再発行しました。\n"
                    . "ユーザー名: " . (string)$u['username'] . "\n"
                    . "メールアドレス: " . (string)$u['email'] . "\n"
                    . "仮パスワード: " . $newPassword . "\n\n"
                    . "ログイン後、必ずパスワードを変更してください。";
                $ok = @mail($email, '[PinkClub-FANZA] Password Reset', $body);
            } else {
                $ok = true;
                $body = 'not-found';
            }

            db()->prepare('INSERT INTO mail_logs(direction,from_name,from_email,to_email,subject,body,status,last_error,created_at,updated_at) VALUES ("out",NULL,:from,:to,:subj,:body,:status,:err,NOW(),NOW())')
                ->execute([':from' => 'noreply@pinkclub.local', ':to' => $email, ':subj' => 'Password Reset', ':body' => $body, ':status' => $ok ? 'sent' : 'failed', ':err' => $ok ? null : 'mail() unavailable']);
            if (!$ok) {
                $message = '現在メールを送信できません。しばらくしてから再度お試しください。';
            }
        }
        if ($message === '') {
            $message = '入力情報を受け付けました。該当ユーザーが存在する場合は再設定案内を送信しました。';
        }
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
            <label>登録メールアドレス</label>
            <input name="email" type="email" required>
            <button type="submit">再発行メールを送る</button>
        </form>
        <div class="login-help"><a href="<?php echo e(login_url()); ?>">ログインへ戻る</a></div>
    </section>
</div>
<?php include __DIR__ . '/partials/login_footer.php';
