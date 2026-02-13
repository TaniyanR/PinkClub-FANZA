<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:users.php');

function send_verification_mail(int $userId, string $email): void
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    db()->prepare('INSERT INTO admin_email_verifications(user_id,token_hash,expires_at,consumed_at,created_at) VALUES(:uid,:hash,DATE_ADD(NOW(), INTERVAL 60 MINUTE),NULL,NOW())')
        ->execute([':uid' => $userId, ':hash' => $hash]);

    $link = base_url() . '/admin/verify_email.php?token=' . rawurlencode($token);
    $subject = '[PinkClub-FANZA] メールアドレス確認';
    $body = "以下のリンクを60分以内に開いて確認してください。\n" . $link;
    $ok = @mail($email, $subject, $body);
    db()->prepare('INSERT INTO mail_logs(direction,from_name,from_email,to_email,subject,body,status,last_error,created_at,updated_at) VALUES ("out",NULL,:from_email,:to_email,:subject,:body,:status,:error,NOW(),NOW())')
        ->execute([
            ':from_email' => 'noreply@pinkclub.local',
            ':to_email' => $email,
            ':subject' => $subject,
            ':body' => $body,
            ':status' => $ok ? 'sent' : 'failed',
            ':error' => $ok ? null : 'mail() unavailable',
        ]);
}

$error = '';
$currentUserId = admin_current_user_id();
$currentUser = [];
if ($currentUserId !== null) {
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $currentUserId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } elseif ($currentUser === []) {
        $error = 'ログイン中ユーザーが見つかりません。';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $password = (string)($_POST['new_password'] ?? '');
        $email = trim((string)($_POST['email'] ?? ''));
        if ($username === '') {
            $error = 'ユーザー名は必須です。';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'メールアドレス形式が正しくありません。';
        } else {
            $verified = !empty($currentUser['email_verified_at']) && !empty($currentUser['email']);
            $loginMode = (string)($currentUser['login_mode'] ?? 'username');
            if ($username !== (string)$currentUser['username'] && $verified) {
                $loginMode = 'email_only';
            }

            db()->prepare('UPDATE admin_users SET username=:username, display_name=:display_name, login_mode=:mode, pending_email=:pending_email, updated_at=NOW() WHERE id=:id')
                ->execute([
                    ':username' => $username,
                    ':display_name' => $displayName,
                    ':mode' => $loginMode,
                    ':pending_email' => $email !== '' ? $email : null,
                    ':id' => (int)$currentUser['id'],
                ]);

            if ($email !== '' && $email !== (string)($currentUser['email'] ?? '') && $email !== (string)($currentUser['pending_email'] ?? '')) {
                send_verification_mail((int)$currentUser['id'], $email);
            }

            if ($password !== '') {
                if (strlen($password) < 8) {
                    $error = 'パスワードは8文字以上で入力してください。';
                } else {
                    db()->prepare('UPDATE admin_users SET password_hash=:hash, updated_at=NOW() WHERE id=:id')
                        ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int)$currentUser['id']]);
                    $_SESSION['admin_default_password'] = false;
                }
            }

            if ($error === '') {
                $_SESSION['admin_user'] = $username;
                admin_flash_set('ok', 'アカウント設定を保存しました。メール変更時は確認リンクを開いてください。');
                header('Location: ' . admin_url('users.php'));
                exit;
            }
        }
    }
}

if ($currentUserId !== null) {
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $currentUserId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
$ok = admin_flash_get('ok');
$pageTitle = 'アカウント設定';
ob_start();
?>
<h1>アカウント設定</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <label>ユーザー名</label>
        <input type="text" name="username" value="<?php echo e((string)($currentUser['username'] ?? '')); ?>" required>

        <label>表示名</label>
        <input type="text" name="display_name" value="<?php echo e((string)($currentUser['display_name'] ?? '')); ?>">

        <label>メールアドレス（保存後に確認メール送信）</label>
        <input type="email" name="email" value="<?php echo e((string)($currentUser['pending_email'] ?? $currentUser['email'] ?? '')); ?>">
        <p>現在のログイン方式: <?php echo e((string)($currentUser['login_mode'] ?? 'username')); ?></p>

        <label>パスワード変更（任意）</label>
        <input type="password" name="new_password" minlength="8" placeholder="変更する場合のみ入力">

        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
admin_trace_push('page:content:ready');
admin_trace_push('page:render:layout');
include __DIR__ . '/../partials/admin_layout.php';
