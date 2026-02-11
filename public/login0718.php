<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/url.php';

admin_session_start();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function normalize_return_to(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $path = trim($value);
    if ($path === '') {
        return '';
    }

    if ($path[0] !== '/') {
        return '';
    }

    if (preg_match('/^\/admin\/[A-Za-z0-9_\/.\-]*$/', $path) !== 1) {
        return '';
    }

    return $path;
}

$returnTo = normalize_return_to($_GET['return_to'] ?? '');

if (admin_is_logged_in()) {
    if (admin_is_default_password()) {
        header('Location: ' . admin_url('change_password.php'));
        exit;
    }

    if ($returnTo !== '') {
        header('Location: ' . base_url() . $returnTo);
        exit;
    }

    header('Location: ' . admin_url('settings.php'));
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $error = '不正なリクエストです。';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $returnTo = normalize_return_to($_POST['return_to'] ?? '');

        if (admin_login($username, $password)) {
            if (admin_is_default_password()) {
                header('Location: ' . admin_url('change_password.php'));
                exit;
            }

            if ($returnTo !== '') {
                header('Location: ' . base_url() . $returnTo);
                exit;
            }

            header('Location: ' . admin_url('settings.php'));
            exit;
        }

        $error = 'ユーザー名またはパスワードが違います。';
    }
}

include __DIR__ . '/partials/header.php';
?>
<main>
    <h1>管理画面ログイン</h1>

    <?php if ($error !== '') : ?>
        <div class="admin-card">
            <p><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <form class="admin-card" method="post" action="<?php echo e(login_url()); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <?php if ($returnTo !== '') : ?>
            <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
        <?php endif; ?>

        <label>ユーザー名</label>
        <input type="text" name="username" autocomplete="username" required>

        <label>パスワード</label>
        <input type="password" name="password" autocomplete="current-password" required>

        <button type="submit">ログイン</button>
    </form>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
