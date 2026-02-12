<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/admin_auth.php';
require_once __DIR__ . '/../lib/local_config_writer.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/partials/_helpers.php';

admin_session_start();

const RESET_MAX_ATTEMPTS = 5;
const RESET_LOCK_SECONDS = 300;

function reset_failures(): int
{
    $count = $_SESSION['forgot_password_failures'] ?? 0;
    return is_int($count) ? max(0, $count) : 0;
}

function reset_locked_until(): int
{
    $until = $_SESSION['forgot_password_locked_until'] ?? 0;
    return is_int($until) ? max(0, $until) : 0;
}

function reset_is_locked(): bool
{
    return reset_locked_until() > time();
}

function reset_remain_seconds(): int
{
    return max(0, reset_locked_until() - time());
}

function reset_register_failure(): void
{
    $failures = reset_failures() + 1;
    $_SESSION['forgot_password_failures'] = $failures;

    if ($failures >= RESET_MAX_ATTEMPTS) {
        $_SESSION['forgot_password_locked_until'] = time() + RESET_LOCK_SECONDS;
        $_SESSION['forgot_password_failures'] = 0;
    }
}

function reset_clear_limit(): void
{
    unset($_SESSION['forgot_password_failures'], $_SESSION['forgot_password_locked_until']);
}

$error = '';
$resetEnabled = false;

$expectedKey = config_get('security.password_reset_key', '');
if (is_string($expectedKey) && trim($expectedKey) !== '') {
    $resetEnabled = true;
    $expectedKey = trim($expectedKey);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? null;

    if (!$resetEnabled) {
        $error = '管理者にお問い合わせください（リセットキー未設定）。';
    } elseif (!csrf_verify(is_string($token) ? $token : null)) {
        $error = '不正なリクエストです。';
    } elseif (reset_is_locked()) {
        $error = '試行回数が上限に達しました。しばらく待ってから再度お試しください。';
    } else {
        $providedKey = trim((string)($_POST['reset_key'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($providedKey === '' || $password === '' || $passwordConfirm === '') {
            $error = 'すべての項目を入力してください。';
            reset_register_failure();
        } elseif (strlen($password) < 8) {
            $error = '新しいパスワードは8文字以上で入力してください。';
            reset_register_failure();
        } elseif ($password !== $passwordConfirm) {
            $error = '新しいパスワード（確認）が一致しません。';
            reset_register_failure();
        } elseif (!hash_equals($expectedKey, $providedKey)) {
            $error = 'リセットキーが正しくありません。';
            reset_register_failure();
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

                reset_clear_limit();
                $_SESSION['admin_logged_in'] = false;
                unset($_SESSION['admin_user'], $_SESSION['admin_default_password']);
                $_SESSION['forgot_password_success'] = 'パスワードを再設定しました。新しいパスワードでログインしてください。';

                header('Location: ' . login_url() . '?reset=1');
                exit;
            } catch (Throwable $e) {
                error_log('forgot password failed: ' . $e->getMessage());
                $error = 'パスワード更新に失敗しました。時間をおいて再度お試しください。';
            }
        }
    }
}

if (reset_is_locked() && $error === '') {
    $error = '試行回数が上限に達しました。しばらく待ってから再度お試しください。';
}

$pageTitle = 'パスワード再設定';
include __DIR__ . '/partials/login_header.php';
?>
    <h1>パスワード再設定</h1>

    <?php if (!$resetEnabled) : ?>
        <div class="admin-card">
            <p><?php echo e('管理者にお問い合わせください（リセットキー未設定）。'); ?></p>
            <p><a href="<?php echo e(login_url()); ?>">ログイン画面に戻る</a></p>
        </div>
    <?php else : ?>
        <?php if ($error !== '') : ?>
            <div class="admin-card">
                <p><?php echo e($error); ?></p>
                <?php if (reset_is_locked()) : ?>
                    <p>あと<?php echo e((string)reset_remain_seconds()); ?>秒ほど待ってから再試行してください。</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form class="admin-card" method="post" action="<?php echo e(base_url() . '/forgot_password.php'); ?>">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

            <label>リセットキー</label>
            <input type="password" name="reset_key" autocomplete="off" required>

            <label>新しいパスワード</label>
            <input type="password" name="password" autocomplete="new-password" minlength="8" required>

            <label>新しいパスワード（確認）</label>
            <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>

            <p>8文字以上で設定してください。</p>
            <button type="submit" <?php echo reset_is_locked() ? 'disabled' : ''; ?>>パスワードを再設定</button>
        </form>
        <p class="login-sub-link"><a href="<?php echo e(login_url()); ?>">ログイン画面に戻る</a></p>
    <?php endif; ?>
<?php include __DIR__ . '/partials/login_footer.php'; ?>
