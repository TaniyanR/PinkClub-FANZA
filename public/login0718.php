<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_session_start();

function normalize_return_to(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $path = trim(mb_substr($value, 0, 255));
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
    if ($returnTo !== '') {
        header('Location: ' . base_url() . $returnTo);
        exit;
    }

    header('Location: ' . admin_url('index.php'));
    exit;
}

$error = '';
$success = '';

if (isset($_SESSION['forgot_password_success']) && is_string($_SESSION['forgot_password_success'])) {
    $success = $_SESSION['forgot_password_success'];
    unset($_SESSION['forgot_password_success']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $error = '不正なリクエストです。';
    } else {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $returnTo = normalize_return_to($_POST['return_to'] ?? '');

        if (admin_login($identifier, $password)) {
            session_regenerate_id(true);

            if ($returnTo !== '') {
                header('Location: ' . base_url() . $returnTo);
                exit;
            }

            header('Location: ' . admin_url('index.php'));
            exit;
        }

        $error = 'ユーザー名またはパスワードが違います。';
    }
}

$pageTitle = '管理画面ログイン';
$hideLoginHeaderBrand = true;
include __DIR__ . '/partials/login_header.php';
?>
    <div class="login-page">
        <div class="login-headline" aria-label="管理画面ログイン見出し">
            <span class="login-headline__item">PinkClub-FANZA</span>
            <span class="login-headline__item">管理画面ログイン</span>
        </div>

        <?php if ($success !== '') : ?>
            <div class="admin-card admin-card--success login-alert">
                <p><?php echo e($success); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error !== '') : ?>
            <div class="admin-card login-alert">
                <p><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="admin-card login-card">
            <form method="post" action="<?php echo e(login_url()); ?>">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                <?php if ($returnTo !== '') : ?>
                    <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
                <?php endif; ?>

                <label>ユーザー名またはメールアドレス</label>
                <input type="text" name="identifier" autocomplete="username" required>

                <label>パスワード</label>
                <input type="password" name="password" autocomplete="current-password" required>

                <button type="submit">ログイン</button>

                <div class="login-help">
                    <a href="<?php echo e(base_url() . '/forgot_password.php'); ?>">パスワードを忘れた方はコチラ</a>
                </div>
            </form>
        </div>
    </div>
<?php include __DIR__ . '/partials/login_footer.php'; ?>
