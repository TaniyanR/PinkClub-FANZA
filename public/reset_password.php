<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_session_start();
db()->exec('CREATE TABLE IF NOT EXISTS password_reset_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,admin_id INT UNSIGNED NOT NULL,token_hash CHAR(64) NOT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,request_ip_hash CHAR(64) NULL,user_agent_hash CHAR(64) NULL,UNIQUE KEY uk_password_reset_token_hash (token_hash),INDEX idx_password_reset_admin (admin_id, created_at),INDEX idx_password_reset_expires (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$token = trim((string)($_GET['token'] ?? ''));
$reset = false;
$stmt = db()->prepare('SELECT * FROM password_reset_tokens WHERE token_hash=:h AND used_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
$stmt->execute([':h' => hash('sha256', $token)]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($reset)) {
    http_response_code(403);
    $pageTitle = '403 Forbidden';
    include __DIR__ . '/partials/login_header.php';
    echo '<div class="login-page"><section class="admin-card login-card"><h1>403 Forbidden</h1><p>リセットトークンが無効または期限切れです。</p><p><a href="' . e(url('/public/forgot_password.php')) . '">再発行へ戻る</a></p></section></div>';
    include __DIR__ . '/partials/login_footer.php';
    exit;
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
        $error = '不正なリクエストです。';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
        if (strlen($password) < 8) {
            $error = '新しいパスワードは8文字以上で入力してください。';
        } elseif ($password !== $passwordConfirm) {
            $error = '確認用パスワードが一致しません。';
        } else {
            $adminUserId = (int)$reset['admin_id'];
            $update = db()->prepare('UPDATE admins SET password_hash=:h, updated_at=NOW() WHERE id=:id');
            $update->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $adminUserId]);
            if ($update->rowCount() < 1) {
                $error = '管理者情報を確認できません。再度申請してください。';
            } else {
                db()->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE id=:id')->execute([':id' => (int)$reset['id']]);
                $_SESSION['forgot_password_success'] = 'パスワードを再設定しました。新しいパスワードでログインしてください。';
                app_redirect(login_url());
            }
        }
    }
}

$pageTitle = 'パスワード再設定';
include __DIR__ . '/partials/login_header.php';
?>
<div class="login-page">
    <div class="login-headline"><span class="login-headline__item">PinkClub-FANZA</span><span class="login-headline__item">パスワード再設定</span></div>
    <?php if ($error !== '') : ?><div class="admin-card login-alert"><p><?php echo e($error); ?></p></div><?php endif; ?>
    <form class="admin-card login-card" method="post" action="<?php echo e(url('/public/reset_password.php?token=' . rawurlencode($token))); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <label>新しいパスワード</label><input type="password" name="password" minlength="8" required>
        <label>新しいパスワード（確認）</label><input type="password" name="password_confirm" minlength="8" required>
        <button type="submit">再設定する</button>
    </form>
</div>
<?php include __DIR__ . '/partials/login_footer.php';
