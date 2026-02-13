<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$error = '';
$currentUsername = (string)(admin_current_user() ?? '');
$currentUser = [];
if ($currentUsername !== '') {
    $stmt = db()->prepare('SELECT id, username, email FROM admin_users WHERE username=:u LIMIT 1');
    $stmt->execute([':u' => $currentUsername]);
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

        if ($username === '') {
            $error = 'ユーザー名は必須です。';
        } else {
            $displayName = $displayName !== '' ? $displayName : $username;
            db()->prepare('UPDATE admin_users SET username=:username, email=:email, updated_at=NOW() WHERE id=:id')
                ->execute([':username' => $username, ':email' => $displayName, ':id' => (int)$currentUser['id']]);

            if ($password !== '') {
                if (strlen($password) < 8) {
                    $error = 'パスワードは8文字以上で入力してください。';
                } else {
                    db()->prepare('UPDATE admin_users SET password_hash=:hash, updated_at=NOW() WHERE id=:id')
                        ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int)$currentUser['id']]);
                }
            }

            if ($error === '') {
                $_SESSION['admin_user'] = $username;
                admin_flash_set('ok', 'アカウント設定を保存しました。');
                header('Location: ' . admin_url('users.php'));
                exit;
            }
        }
    }
}

if ($currentUser !== []) {
    $currentUser['display_name'] = (string)($currentUser['email'] ?? $currentUser['username']);
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

        <label>表示名</label>
        <input type="text" name="display_name" value="<?php echo e((string)($currentUser['display_name'] ?? '')); ?>">

        <label>ユーザー名</label>
        <input type="text" name="username" value="<?php echo e((string)($currentUser['username'] ?? '')); ?>" required>

        <label>パスワード変更（任意）</label>
        <input type="password" name="new_password" minlength="8" placeholder="変更する場合のみ入力">

        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
