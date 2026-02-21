<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_session_start();

$returnTo = normalize_admin_redirect_target((string)($_GET['return_to'] ?? ''));

if (admin_is_logged_in()) {
    app_redirect(admin_path('index.php'));
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string)($_POST['identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (admin_login($username, $password)) {
        session_regenerate_id(true);
        header('Location: ' . admin_path('index.php'));
        exit;
    }

    $error = 'ユーザー名またはパスワードが違います。';
}

$pageTitle = '管理画面ログイン';
$hideLoginHeaderBrand = true;
include __DIR__ . '/partials/login_header.php';
?>
    <div class="login-page">
        <div class="login-headline" aria-label="管理画面ログイン見出し">
            <span class="login-headline__item"><?php echo e(site_title_setting('') !== '' ? site_title_setting('') : 'サイトタイトル未設定'); ?></span>
            <span class="login-headline__item">管理画面ログイン</span>
        </div>

        <?php if ($error !== '') : ?>
            <div class="admin-card login-alert">
                <p><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="admin-card login-card">
            <form method="post" action="<?php echo e(login_path()); ?>">
                <?php if ($returnTo !== '') : ?>
                    <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
                <?php endif; ?>

                <label>ユーザー名</label>
                <input type="text" name="identifier" autocomplete="username" required>

                <label>パスワード</label>
                <input type="password" name="password" autocomplete="current-password" required>

                <button type="submit">ログイン</button>

                <div class="login-help">
                    <a href="<?php echo e(base_url() . '/forgot_password.php'); ?>">パスワードを忘れた方はコチラ</a>
                </div>
            </form>

            <!-- TODO(next): CSRF対応 / Remember me / パスワード再発行 / 監査ログを段階的に戻す -->
        </div>
    </div>
<?php include __DIR__ . '/partials/login_footer.php'; ?>
