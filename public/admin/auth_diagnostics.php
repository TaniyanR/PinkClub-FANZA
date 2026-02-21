<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/db.php';

function auth_diag_label(bool $ok): string
{
    return $ok ? 'OK' : 'NG';
}

admin_session_start();

$sessionStatus = session_status();
$sessionStarted = $sessionStatus === PHP_SESSION_ACTIVE;
$sessionSavePath = (string)session_save_path();
$sessionName = session_name();
$sessionId = session_id();
$sessionUser = $_SESSION['admin_user'] ?? null;

$headersSent = headers_sent($headersFile, $headersLine);
$headersSentLocation = $headersSent ? ($headersFile . ':' . (string)$headersLine) : '未送信';

$dbOk = false;
$tableOk = false;
$adminUsersCount = 0;
$dbError = '';
$currentUser = null;

try {
    $pdo = db();
    $dbOk = true;

    $tableStmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    $tableOk = $tableStmt !== false && $tableStmt->fetchColumn() !== false;

    if ($tableOk) {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
        $adminUsersCount = (int)$countStmt->fetchColumn();
    }
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
    error_log('[auth_diagnostics] ' . $exception->getMessage());
}

try {
    $currentUser = admin_current_user();
} catch (Throwable $exception) {
    error_log('[auth_diagnostics] admin_current_user failed: ' . $exception->getMessage());
}

$adminCurrentUserOk = is_array($currentUser) && (int)($currentUser['id'] ?? 0) > 0;
$sessionUserSafe = is_array($sessionUser)
    ? [
        'id' => (int)($sessionUser['id'] ?? 0),
        'username' => (string)($sessionUser['username'] ?? ''),
        'email' => (string)($sessionUser['email'] ?? ''),
    ]
    : null;

$pageTitle = '認証診断';
ob_start();
?>
<h1>認証診断</h1>
<div class="admin-card">
    <ul>
        <li>セッション開始状態: <?php echo e(auth_diag_label($sessionStarted)); ?> (status=<?php echo e((string)$sessionStatus); ?>)</li>
        <li>session.name: <?php echo e($sessionName !== '' ? $sessionName : '(empty)'); ?></li>
        <li>session.id: <?php echo e($sessionId !== '' ? $sessionId : '(empty)'); ?></li>
        <li>session.save_path: <?php echo e($sessionSavePath !== '' ? $sessionSavePath : '(empty)'); ?></li>
        <li>headers_sent: <?php echo e($headersSent ? 'YES' : 'NO'); ?> (<?php echo e($headersSentLocation); ?>)</li>
        <li><code>$_SESSION['admin_user']</code> の有無: <?php echo e(auth_diag_label($sessionUserSafe !== null)); ?></li>
        <li><code>$_SESSION['admin_user']</code> (安全表示): <pre><?php echo e((string)json_encode($sessionUserSafe, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre></li>
        <li>DB接続: <?php echo e(auth_diag_label($dbOk)); ?></li>
        <li><code>admin_users</code> テーブル存在: <?php echo e(auth_diag_label($tableOk)); ?></li>
        <li><code>admin_users</code> 件数: <?php echo e((string)$adminUsersCount); ?></li>
        <li><code>admin_current_user()</code> 取得: <?php echo e(auth_diag_label($adminCurrentUserOk)); ?></li>
        <li>現在ログインユーザー: <?php echo e($adminCurrentUserOk ? ('id=' . (string)$currentUser['id'] . ' / username=' . (string)$currentUser['username']) : '取得できません'); ?></li>
        <li>localhost認証バイパス(設定): <?php echo e(admin_dev_auth_bypass_enabled() ? '有効' : '無効'); ?></li>
        <li>localhost認証バイパス(現在セッション): <?php echo e(admin_dev_auth_bypass_active() ? '有効' : '無効'); ?></li>
        <li>session.cookie_httponly: <?php echo e((string)ini_get('session.cookie_httponly')); ?></li>
        <li>session.cookie_secure: <?php echo e((string)ini_get('session.cookie_secure')); ?></li>
        <li>session.cookie_samesite: <?php echo e((string)ini_get('session.cookie_samesite')); ?></li>
        <li>session.use_strict_mode: <?php echo e((string)ini_get('session.use_strict_mode')); ?></li>
        <li>session.gc_maxlifetime: <?php echo e((string)ini_get('session.gc_maxlifetime')); ?></li>
        <?php if ($dbError !== '') : ?>
            <li>DBエラー詳細: <?php echo e($dbError); ?></li>
        <?php endif; ?>
    </ul>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
