<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/db.php';


$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $error = 'CSRFトークンが無効です。';
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
                $currentAdmin = admin_current_user();
                $adminId = is_array($currentAdmin) ? (int)($currentAdmin['id'] ?? 0) : 0;
                if ($adminId <= 0) {
                    $error = '現在のログインユーザーを特定できません。再ログイン後にお試しください。';
                } else {
                    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id=:id AND is_active=1 LIMIT 1');
                    $stmt->execute([':id' => $adminId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!is_array($row)) {
                        $error = 'ログインユーザー情報が見つかりません。再ログインしてください。';
                    } else {
                        $verifyOk = false;
                        $passwordHash = (string)($row['password_hash'] ?? '');
                        if ($passwordHash !== '' && password_verify($currentPassword, $passwordHash)) {
                            $verifyOk = true;
                        }

                        if (!$verifyOk && array_key_exists('password', $row)) {
                            $legacyPassword = (string)($row['password'] ?? '');
                            if ($legacyPassword !== '' && hash_equals($legacyPassword, $currentPassword)) {
                                $verifyOk = true;
                            }
                        }

                        if (!$verifyOk) {
                            $error = '現在のパスワードが正しくありません。';
                        } else {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $updated = db()->prepare('UPDATE admin_users SET password_hash=:p, updated_at=NOW() WHERE id=:id LIMIT 1')
                                ->execute([':p' => $newHash, ':id' => (int)$row['id']]);
                            if ($updated !== true) {
                                $error = 'DB更新に失敗しました。';
                            }
                        }
                    }
                }

                if ($updated) {
                    if (session_status() !== PHP_SESSION_ACTIVE) {
                        session_start();
                    }
                    if (isset($_SESSION['admin_user']) && is_array($_SESSION['admin_user'])) {
                        $_SESSION['admin_user']['password_hash'] = $newHash;
                    }
                    header('Location: ' . admin_url('settings.php?tab=site&password_changed=1'));
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
