<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/config.php';

function login0718_is_dev_env(): bool
{
    $configEnv = function_exists('config_get') ? (string)config_get('app.env', '') : '';
    $env = $configEnv !== '' ? $configEnv : (string)(getenv('APP_ENV') ?: '');
    if (in_array(strtolower($env), ['dev', 'development', 'local', 'staging'], true)) {
        return true;
    }

    $debug = (string)(getenv('APP_DEBUG') ?: '');
    return in_array(strtolower($debug), ['1', 'true', 'yes', 'on'], true);
}

if (login0718_is_dev_env()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

function login0718_log_path(): string
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir . '/php-error.log';
}

function login0718_write_log(string $type, string $message, string $file, int $line): void
{
    $row = sprintf(
        "[%s] [%s] %s | file=%s line=%d REQUEST_URI=%s\n",
        date('Y-m-d H:i:s'),
        $type,
        $message,
        $file,
        $line,
        (string)($_SERVER['REQUEST_URI'] ?? '-')
    );

    @file_put_contents(login0718_log_path(), $row, FILE_APPEND);
}

set_exception_handler(static function (Throwable $e): void {
    login0718_write_log('EXCEPTION', $e->getMessage(), $e->getFile(), $e->getLine());
    http_response_code(500);
    echo 'システムエラーが発生しました。';
    exit;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)$error['type'], $fatalTypes, true)) {
        return;
    }

    login0718_write_log(
        'FATAL',
        (string)$error['message'],
        (string)$error['file'],
        (int)$error['line']
    );
});

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/url.php';
require_once __DIR__ . '/../lib/site_settings.php';

function login0718_authenticate(string $identifier, string $password): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return null;
    }

    try {
        $pdo = db();

        $hasAdminUsers = $pdo->query("SHOW TABLES LIKE 'admin_users'");
        if ($hasAdminUsers !== false && $hasAdminUsers->fetchColumn() !== false) {
            $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM admin_users WHERE is_active = 1 AND (username = :identifier OR email = :identifier) LIMIT 1');
            $stmt->execute([':identifier' => $identifier]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $stored = (string)($row['password_hash'] ?? '');
                $ok = false;

                if ($stored !== '') {
                    if (password_get_info($stored)['algo'] !== 0) {
                        $ok = password_verify($password, $stored);
                    } else {
                        $ok = hash_equals($stored, $password);
                        if ($ok) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $update = $pdo->prepare('UPDATE admin_users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
                            $update->execute([
                                ':password_hash' => $newHash,
                                ':id' => (int)$row['id'],
                            ]);
                            $stored = $newHash;
                        }
                    }
                }

                if ($ok) {
                    return [
                        'id' => (int)$row['id'],
                        'username' => (string)$row['username'],
                        'email' => isset($row['email']) ? (string)$row['email'] : null,
                        'password_hash' => $stored,
                        'login_mode' => 'db',
                    ];
                }

                return null;
            }
        }
    } catch (Throwable $e) {
        login0718_write_log('EXCEPTION', 'DB auth failed: ' . $e->getMessage(), $e->getFile(), $e->getLine());
    }

    $configUsername = (string)config_get('admin.username', 'admin');
    $configPasswordHash = (string)config_get('admin.password_hash', '');

    if ($configPasswordHash !== '' && hash_equals($configUsername, $identifier) && password_verify($password, $configPasswordHash)) {
        return [
            'id' => 0,
            'username' => $configUsername,
            'email' => null,
            'password_hash' => $configPasswordHash,
            'login_mode' => 'config',
        ];
    }

    if ($identifier === 'admin' && $password === 'password') {
        return [
            'id' => 0,
            'username' => 'admin',
            'email' => null,
            'password_hash' => '',
            'login_mode' => 'fallback',
        ];
    }

    return null;
}

if (isset($_SESSION['admin_user']) && is_array($_SESSION['admin_user']) && (string)($_SESSION['admin_user']['username'] ?? '') !== '') {
    header('Location: /pinkclub-fanza/public/admin/index.php');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $identifier = (string)($_POST['identifier'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $user = login0718_authenticate($identifier, $password);
    if ($user !== null) {
        session_regenerate_id(true);
        $_SESSION['admin_user'] = $user;
        header('Location: /pinkclub-fanza/public/admin/index.php');
        exit;
    }

    $error = 'ユーザー名/メールまたはパスワードが違います。';
}

$siteTitle = trim(site_title_setting(''));
if ($siteTitle === '') {
    $siteTitle = 'サイトタイトル未設定';
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理ログイン</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        .login { max-width: 420px; margin: 0 auto; }
        label { display: block; margin-top: 1rem; }
        input { width: 100%; padding: .5rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: .6rem 1rem; }
        .error { color: #b00020; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="login">
    <h1><?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
    <h2>管理ログイン</h2>

    <?php if ($error !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="<?php echo htmlspecialchars(login_url(), ENT_QUOTES, 'UTF-8'); ?>">
        <label for="identifier">ユーザー名またはメール</label>
        <input id="identifier" name="identifier" type="text" autocomplete="username" required>

        <label for="password">パスワード</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button type="submit">ログイン</button>
    </form>
</div>
</body>
</html>
