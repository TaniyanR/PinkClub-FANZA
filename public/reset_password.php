<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/local_config_writer.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_session_start();

$configuredToken = trim((string)config_get('admin_reset_token', ''));
$providedToken = trim((string)($_GET['token'] ?? ''));

if ($configuredToken === '' || $providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
    http_response_code(403);
    $pageTitle = '403 Forbidden';
    include __DIR__ . '/partials/login_header.php';
    echo '<div class="login-page"><section class="admin-card login-card"><h1>403 Forbidden</h1><p>リセットトークンが無効です。</p><p><a href="' . e(base_url() . '/forgot_password.php') . '">案内ページへ戻る</a></p></section></div>';
    include __DIR__ . '/partials/login_footer.php';
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $error = '不正なリクエストです。';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $error = '新しいパスワードは8文字以上で入力してください。';
        } elseif ($password !== $passwordConfirm) {
            $error = '確認用パスワードが一致しません。';
        } else {
            try {
                $local = local_config_load();
                $admin = is_array($local['admin'] ?? null) ? $local['admin'] : [];
                $currentAdmin = admin_config();

                $admin['username'] = is_string($admin['username'] ?? null) && $admin['username'] !== ''
                    ? $admin['username']
                    : $currentAdmin['username'];
                $admin['password_hash'] = password_hash($password, PASSWORD_DEFAULT);

                $local['admin'] = $admin;
                local_config_write($local);

                $_SESSION['forgot_password_success'] = 'パスワードを再設定しました。新しいパスワードでログインしてください。';
                $_SESSION['admin_logged_in'] = false;
                unset($_SESSION['admin_user'], $_SESSION['admin_default_password']);

                header('Location: ' . login_url());
                exit;
            } catch (Throwable $e) {
                log_message('[reset_password] ' . $e->getMessage());
                $error = 'パスワード更新に失敗しました。権限や設定を確認してください。';
            }
        }
    }
}

$pageTitle = 'パスワード再設定';
include __DIR__ . '/partials/login_header.php';
?>
<div class="login-page">
    <div class="login-headline" aria-label="パスワード再設定見出し">
        <span class="login-headline__item">PinkClub-FANZA</span>
        <span class="login-headline__item">パスワード再設定</span>
    </div>

    <?php if ($error !== '') : ?>
        <div class="admin-card login-alert"><p><?php echo e($error); ?></p></div>
    <?php endif; ?>

    <form class="admin-card login-card" method="post" action="<?php echo e(base_url() . '/reset_password.php?token=' . rawurlencode($providedToken)); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <label>新しいパスワード</label>
        <input type="password" name="password" autocomplete="new-password" minlength="8" required>

        <label>新しいパスワード（確認）</label>
        <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>

        <button type="submit">再設定する</button>

        <div class="login-help">
            <a href="<?php echo e(login_url()); ?>">ログイン画面に戻る</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/partials/login_footer.php'; ?>
