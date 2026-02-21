<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/db.php';

function auth_diag_label(bool $ok): string
{
    return $ok ? 'OK' : 'NG';
}

start_admin_session();

$sessionStarted = session_status() === PHP_SESSION_ACTIVE;
$sessionUserExists = isset($_SESSION['admin_user']) && is_array($_SESSION['admin_user']);
$sessionUserIdExists = $sessionUserExists && (int)($_SESSION['admin_user']['id'] ?? 0) > 0;
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

$pageTitle = '認証診断';
ob_start();
?>
<h1>認証診断</h1>
<div class="admin-card">
    <ul>
        <li>セッション開始状態: <?php echo e(auth_diag_label($sessionStarted)); ?></li>
        <li><code>$_SESSION['admin_user']</code> の有無: <?php echo e(auth_diag_label($sessionUserExists)); ?></li>
        <li><code>admin_user.id</code> の有無: <?php echo e(auth_diag_label($sessionUserIdExists)); ?></li>
        <li>DB接続: <?php echo e(auth_diag_label($dbOk)); ?></li>
        <li><code>admin_users</code> テーブル存在: <?php echo e(auth_diag_label($tableOk)); ?></li>
        <li><code>admin_users</code> 件数: <?php echo e((string)$adminUsersCount); ?></li>
        <li><code>admin_current_user()</code> 取得: <?php echo e(auth_diag_label($adminCurrentUserOk)); ?></li>
        <li>現在ログインユーザー: <?php echo e($adminCurrentUserOk ? ('id=' . (string)$currentUser['id'] . ' / username=' . (string)$currentUser['username']) : '取得できません'); ?></li>
        <li>localhost認証バイパス: <?php echo e(function_exists('admin_dev_auth_bypass_active') && admin_dev_auth_bypass_active() ? '有効' : '無効'); ?></li>
        <?php if ($dbError !== '') : ?>
            <li>DBエラー詳細: <?php echo e($dbError); ?></li>
        <?php endif; ?>
    </ul>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
