<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/admin_auth_v2.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_v2_session_start();

if (admin_v2_is_logged_in()) {
    app_redirect('/admin/index.php');
}

$error = '';
$identifier = '';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
    try {
        if (!admin_v2_csrf_verify(is_string($_POST['_token'] ?? null) ? (string)$_POST['_token'] : null)) {
            $error = 'セッションの確認に失敗しました。もう一度お試しください。';
        } else {
            $identifier = trim((string)($_POST['identifier'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if (admin_v2_login($identifier, $password)) {
                app_redirect('/admin/index.php');
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
            <input type="hidden" name="_token" value="<?php echo e(admin_v2_csrf_token()); ?>">

            <label for="identifier">ユーザー名またはメール</label>
            <input id="identifier" name="identifier" type="text" autocomplete="username" value="<?php echo e($identifier); ?>" required>

            <label for="password">パスワード</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button type="submit">ログイン</button>
        </form>
    </section>
</div>
<?php include __DIR__ . '/partials/login_footer.php';
