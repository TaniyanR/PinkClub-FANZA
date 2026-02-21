<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/partials/_helpers.php';

start_admin_session();

if (admin_is_logged_in()) {
    app_redirect(admin_path('index.php'));
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['_token'] ?? ''))) {
            $error = 'CSRFトークンが無効です。';
        } else {
            $identifier = trim((string)($_POST['identifier'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if (admin_login($identifier, $password)) {
                app_redirect(admin_path('index.php'));
            }

            $error = 'ユーザー名/メールまたはパスワードが違います。';
        }
    } catch (Throwable $exception) {
        error_log('[login0718] login error: ' . $exception->getMessage());
        $error = 'ログイン処理中にエラーが発生しました。時間をおいて再度お試しください。';
    }
}

$pageTitle = '管理ログイン';
include __DIR__ . '/partials/login_header.php';
?>
<div class="login-page">
    <div class="login-headline">
        <span class="login-headline__item">PinkClub-FANZA</span>
        <span class="login-headline__item">管理ログイン</span>
    </div>

    <?php if ($error !== '') : ?>
        <div class="admin-card login-alert"><p><?php echo e($error); ?></p></div>
    <?php endif; ?>

    <section class="admin-card login-card">
        <form method="post" action="<?php echo e(login_path()); ?>">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

            <label for="identifier">ユーザー名またはメール</label>
            <input id="identifier" name="identifier" type="text" autocomplete="username" required>

            <label for="password">パスワード</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit">ログイン</button>
        </form>

        <div class="login-help"><a href="<?php echo e(base_url() . '/forgot_password.php'); ?>">パスワードを忘れた場合</a></div>
    </section>
</div>
<?php include __DIR__ . '/partials/login_footer.php';
