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

function users_current_admin(): array
{
    $sessionUser = admin_current_user();
    $currentUserId = is_array($sessionUser) ? (int)($sessionUser['id'] ?? 0) : 0;
    if ($currentUserId <= 0) {
        return [];
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $currentUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

function users_set_session(array $user): void
{
    $_SESSION['admin_user'] = [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'email' => isset($user['email']) && is_string($user['email']) ? $user['email'] : null,
        'login_mode' => isset($user['login_mode']) && is_string($user['login_mode']) ? $user['login_mode'] : 'username',
        'password_hash' => isset($user['password_hash']) && is_string($user['password_hash']) ? $user['password_hash'] : '',
    ];
}

$error = '';
$currentUser = users_current_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } elseif ($currentUser === []) {
        $error = 'ログイン中ユーザーが見つかりません。';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'update_email') {
            $email = trim((string)($_POST['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'メールアドレス形式が正しくありません。';
            } else {
                if ($email === '') {
                    db()->prepare('UPDATE admin_users SET email=NULL, pending_email=NULL, email_verified_at=NULL, login_mode="username", updated_at=NOW() WHERE id=:id')
                        ->execute([':id' => (int)$currentUser['id']]);
                    header('Location: ' . admin_url('users.php?ok=email_updated'));
                    exit;
                }

                if (
                    $email === (string)($currentUser['email'] ?? '')
                    && (string)($currentUser['pending_email'] ?? '') === ''
                ) {
                    header('Location: ' . admin_url('users.php?ok=email_updated'));
                    exit;
                }

                db()->prepare('UPDATE admin_users SET pending_email=:pending_email, updated_at=NOW() WHERE id=:id')
                    ->execute([':pending_email' => $email, ':id' => (int)$currentUser['id']]);
                send_verification_mail((int)$currentUser['id'], $email);
                header('Location: ' . admin_url('users.php?ok=email_updated'));
                exit;
            }
        } elseif ($action === 'update_password') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['new_password_confirm'] ?? '');

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $error = 'パスワード変更に必要な項目を入力してください。';
            } elseif (!password_verify($currentPassword, (string)($currentUser['password_hash'] ?? ''))) {
                $error = '現在のパスワードが正しくありません。';
            } elseif (strlen($newPassword) < 8) {
                $error = '新しいパスワードは8文字以上で入力してください。';
            } elseif ($newPassword !== $confirmPassword) {
                $error = '新しいパスワードが一致しません。';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                db()->prepare('UPDATE admin_users SET password_hash=:hash, updated_at=NOW() WHERE id=:id')
                    ->execute([':hash' => $passwordHash, ':id' => (int)$currentUser['id']]);
                $_SESSION['admin_default_password'] = false;
                header('Location: ' . admin_url('users.php?ok=pass_updated'));
                exit;
            }
        } elseif ($action === 'update_username') {
            $username = trim((string)($_POST['username'] ?? ''));
            if (preg_match('/^[A-Za-z0-9_-]{3,30}$/', $username) !== 1) {
                $error = 'ユーザー名は3〜30文字の英数字・アンダースコア・ハイフンで入力してください。';
            } elseif ($username !== (string)$currentUser['username']) {
                $stmt = db()->prepare('SELECT id FROM admin_users WHERE username=:username AND id<>:id LIMIT 1');
                $stmt->execute([':username' => $username, ':id' => (int)$currentUser['id']]);
                if ($stmt->fetchColumn() !== false) {
                    $error = 'そのユーザー名は既に使用されています。';
                }
            }

            if ($error === '') {
                db()->prepare('UPDATE admin_users SET username=:username, updated_at=NOW() WHERE id=:id')
                    ->execute([':username' => $username, ':id' => (int)$currentUser['id']]);
                $currentUser['username'] = $username;
                users_set_session($currentUser);
                header('Location: ' . admin_url('users.php?ok=username_updated'));
                exit;
            }
        } else {
            $error = '不明な操作です。';
        }
    }
}

$currentUser = users_current_admin();
$ok = (string)($_GET['ok'] ?? '');
$okMessage = '';
if ($ok === 'email_updated') {
    $okMessage = 'メール設定を更新しました。メール変更時は確認リンクを開いてください。';
} elseif ($ok === 'pass_updated') {
    $okMessage = 'パスワードを更新しました。';
} elseif ($ok === 'username_updated') {
    $okMessage = 'ユーザー名を更新しました。';
}

$pageTitle = 'アカウント設定';
ob_start();
?>
<h1>アカウント設定</h1>
<?php if ($okMessage !== '') : ?><div class="admin-card"><p><?php echo e($okMessage); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>

<div class="admin-card">
    <h2>現在の情報</h2>
    <p><strong>username:</strong> <?php echo e((string)($currentUser['username'] ?? '')); ?></p>
    <p><strong>email:</strong> <?php echo e((string)($currentUser['email'] ?? '')); ?></p>
</div>

<div class="admin-card">
    <h2>メール更新</h2>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="update_email">
        <label>メールアドレス</label>
        <input type="email" name="email" value="<?php echo e((string)($currentUser['pending_email'] ?? $currentUser['email'] ?? '')); ?>" placeholder="example@domain.com">
        <button type="submit">メールを更新</button>
    </form>
</div>

<div class="admin-card">
    <h2>パスワード変更</h2>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="update_password">
        <label>現在のパスワード</label>
        <input type="password" name="current_password" autocomplete="current-password" required>
        <label>新しいパスワード（8文字以上）</label>
        <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
        <label>新しいパスワード（確認）</label>
        <input type="password" name="new_password_confirm" autocomplete="new-password" minlength="8" required>
        <button type="submit">パスワードを更新</button>
    </form>
</div>

<div class="admin-card">
    <h2>username変更</h2>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="update_username">
        <label>新しいusername（3〜30文字, 英数字/ _ / - ）</label>
        <input type="text" name="username" value="<?php echo e((string)($currentUser['username'] ?? '')); ?>" required>
        <button type="submit">usernameを更新</button>
    </form>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
