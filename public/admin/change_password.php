<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/local_config_writer.php';
require_once __DIR__ . '/../../lib/db.php';


$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $error = '不正なリクエストです。';
    } else {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($currentPassword === '' || $password === '' || $passwordConfirm === '') {
            $error = 'すべての項目を入力してください。';
        } elseif (strlen($password) < 8) {
            $error = '新しいパスワードは8文字以上で入力してください。';
        } elseif ($password !== $passwordConfirm) {
            $error = '新しいパスワードが一致しません。';
        } elseif (preg_match('/\s/u', $password) === 1) {
            $error = 'パスワードに空白は使用できません。';
        } else {
            try {
                $updated = false;
                try {
                    $stmt = db()->prepare('SELECT id,password_hash FROM admin_users WHERE username=:u AND is_active=1 LIMIT 1');
                    $stmt->execute([':u' => (string)(admin_current_user() ?? '')]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (is_array($row)) {
                        if (!password_verify($currentPassword, (string)$row['password_hash'])) {
                            $error = '現在のパスワードが正しくありません。';
                        } else {
                            db()->prepare('UPDATE admin_users SET password_hash=:p WHERE id=:id')->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int)$row['id']]);
                            $updated = true;
                        }
                    }
                } catch (Throwable $e) {
                }

                if (!$updated && $error === '') {
                    if (!password_verify($currentPassword, admin_config()['password_hash'])) {
                        $error = '現在のパスワードが正しくありません。';
                    } else {
                        $local = local_config_load();
                        $admin = is_array($local['admin'] ?? null) ? $local['admin'] : [];
                        $admin['username'] = admin_current_user() ?? ADMIN_DEFAULT_USERNAME;
                        $admin['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                        $local['admin'] = $admin;
                        local_config_write($local);
                        $updated = true;
                    }
                }

                if ($updated) {
                    $_SESSION['admin_default_password'] = false;
                    header('Location: ' . admin_url('settings.php') . '?password_changed=1');
                    exit;
                }
            } catch (Throwable $e) {
                error_log('admin change_password failed: ' . $e->getMessage());
                $error = 'パスワード更新に失敗しました。時間をおいて再度お試しください。';
            }
        }
    }
}

$pageTitle = 'パスワード変更';
ob_start();
?>
    <h1>管理者パスワード変更</h1>

    <?php if ($error !== '') : ?>
        <div class="admin-card">
            <p><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <form class="admin-card" method="post" action="<?php echo e(admin_url('change_password.php')); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <label>現在のパスワード</label>
        <input type="password" name="current_password" autocomplete="current-password" required>

        <label>新しいパスワード</label>
        <input type="password" name="password" autocomplete="new-password" minlength="8" required>

        <label>新しいパスワード（確認）</label>
        <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>

        <p>8文字以上・空白なしで設定してください。</p>
        <button type="submit">パスワードを更新</button>
    </form>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
