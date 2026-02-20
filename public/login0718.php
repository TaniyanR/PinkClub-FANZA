<?php
declare(strict_types=1);

// 開発時のみ有効化: 白画面(Fatal)切り分け用
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/admin_auth_simple.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_simple_session_start();

function redirect_to(string $url, int $status = 302): never
{
    if (!headers_sent()) {
        header('Location: ' . $url, true, $status);
        exit;
    }

    $safeUrl = e($url);
    http_response_code($status);
    echo '<!doctype html><html lang="ja"><head><meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
    echo '<title>Redirecting...</title></head><body>';
    echo '<p>画面を移動します。自動で移動しない場合は <a href="' . $safeUrl . '">こちら</a> をクリックしてください。</p>';
    echo '<script>location.replace(' . json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');</script>';
    echo '</body></html>';
    exit;
}

$returnTo = normalize_admin_redirect_target((string)($_GET['return_to'] ?? ''));

if (admin_simple_is_logged_in()) {
    redirect_to(admin_path('index.php'));
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string)($_POST['identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (admin_simple_verify_credentials($username, $password)) {
        admin_simple_login($username);
        $postedReturnTo = normalize_admin_redirect_target((string)($_POST['return_to'] ?? ''));
        redirect_to($postedReturnTo !== '' ? $postedReturnTo : admin_path('index.php'), 303);
    }

    $error = 'ユーザー名またはパスワードが違います。';
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
