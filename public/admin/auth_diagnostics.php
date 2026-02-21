<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../lib/url.php';
require_once __DIR__ . '/../../lib/admin_auth_v2.php';
require_once __DIR__ . '/../../lib/db.php';

admin_v2_session_start();

function diag_h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function diag_bool(bool $v): string
{
    return $v ? 'OK' : 'NG';
}

$headersSent = headers_sent($headersFile, $headersLine);
$headersWhere = $headersSent ? ($headersFile . ':' . (string)$headersLine) : 'not sent';
$sessionUser = $_SESSION[ADMIN_V2_SESSION_KEY] ?? null;
$sessionSafe = is_array($sessionUser) ? [
    'id' => (int)($sessionUser['id'] ?? 0),
    'username' => (string)($sessionUser['username'] ?? ''),
    'email' => (string)($sessionUser['email'] ?? ''),
] : null;

$dbOk = false;
$tableOk = false;
$adminCount = 0;
$adminExists = false;
$dbError = '';

try {
    $pdo = db();
    $dbOk = true;

    $tableStmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    $tableOk = $tableStmt !== false && $tableStmt->fetchColumn() !== false;

    if ($tableOk) {
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        $adminExistsStmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
        $adminExistsStmt->execute([':username' => 'admin']);
        $adminExists = $adminExistsStmt->fetchColumn() !== false;
    }
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
    error_log('[auth_diagnostics] ' . $exception->getMessage());
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>認証診断</title>
    <style>body{font-family:system-ui,-apple-system,sans-serif;line-height:1.5;padding:24px}code,pre{background:#f5f5f5;padding:2px 6px}pre{padding:12px}</style>
</head>
<body>
<h1>認証診断 (login不要)</h1>
<ul>
    <li>PHP version: <?php echo diag_h(PHP_VERSION); ?></li>
    <li>session_status(): <?php echo diag_h((string)session_status()); ?></li>
    <li>session.save_path: <?php echo diag_h((string)session_save_path()); ?></li>
    <li>session.name: <?php echo diag_h((string)session_name()); ?></li>
    <li>session.cookie_httponly: <?php echo diag_h((string)ini_get('session.cookie_httponly')); ?></li>
    <li>session.cookie_secure: <?php echo diag_h((string)ini_get('session.cookie_secure')); ?></li>
    <li>session.cookie_samesite: <?php echo diag_h((string)ini_get('session.cookie_samesite')); ?></li>
    <li>headers_sent(): <?php echo diag_h($headersSent ? 'YES' : 'NO'); ?> (<?php echo diag_h($headersWhere); ?>)</li>
    <li>$_SESSION safe dump: <pre><?php echo diag_h((string)json_encode($sessionSafe, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre></li>
    <li>認証セッションキー(admin_user): <?php echo diag_h(diag_bool(is_array($sessionSafe))); ?></li>
    <li>DB接続: <?php echo diag_h(diag_bool($dbOk)); ?></li>
    <li>admin_usersテーブル: <?php echo diag_h(diag_bool($tableOk)); ?></li>
    <li>adminユーザー件数: <?php echo diag_h((string)$adminCount); ?></li>
    <li>adminユーザー存在: <?php echo diag_h(diag_bool($adminExists)); ?></li>
    <li>base_url(): <?php echo diag_h(base_url()); ?></li>
    <li>base_path(): <?php echo diag_h(base_path()); ?></li>
    <li>login_path(): <?php echo diag_h(login_path()); ?></li>
    <li>HTTP_HOST: <?php echo diag_h((string)($_SERVER['HTTP_HOST'] ?? '')); ?></li>
    <li>REQUEST_URI: <?php echo diag_h((string)($_SERVER['REQUEST_URI'] ?? '')); ?></li>
    <li>SCRIPT_NAME: <?php echo diag_h((string)($_SERVER['SCRIPT_NAME'] ?? '')); ?></li>
    <li>dev bypass: 無効（固定）</li>
    <?php if ($dbError !== '') : ?>
    <li>DB error(logged): <?php echo diag_h($dbError); ?></li>
    <?php endif; ?>
</ul>
</body>
</html>
