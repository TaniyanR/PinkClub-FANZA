<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../scripts/init_db.php';


$status = '';
$error = '';
$isDevEnvironment = strtolower((string)config_get('app.env', '')) === 'dev';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $error = '不正なリクエストです。';
    } else {
        $action = (string)($_POST['action'] ?? 'init_db');

        if ($action === 'reset_admin_password') {
            if (!$isDevEnvironment) {
                $error = 'この操作は開発環境でのみ利用できます。';
            } elseif (!admin_users_table_available()) {
                $error = 'admin_users テーブルが存在しません。';
            } else {
                try {
                    $stmt = db()->prepare('UPDATE admin_users SET password_hash=:hash, updated_at=NOW() WHERE username=:username LIMIT 1');
                    $stmt->execute([
                        ':hash' => password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_DEFAULT),
                        ':username' => ADMIN_DEFAULT_USERNAME,
                    ]);
                    log_message(sprintf('[admin_db_init] dev admin password reset executed | affected_rows=%d', $stmt->rowCount()));
                    $status = 'admin パスワードを初期化しました。';
                } catch (Throwable $e) {
                    error_log('admin password reset failed: ' . $e->getMessage());
                    $error = 'admin パスワードの初期化に失敗しました。ログを確認してください。';
                }
            }
        } else {
            try {
                $result = init_db();
                $status = sprintf('スキーマ再適用が完了しました。（%s使用: %dステートメント）', $result['source'], $result['count']);
            } catch (Throwable $e) {
                error_log('db_init failed: ' . $e->getMessage());
                $error = 'スキーマ再適用に失敗しました。ログを確認してください。';
            }
        }
    }
}

$pageTitle = 'DBメンテナンス';
ob_start();
?>
    <h1>DBメンテナンス</h1>
    <p>通常運用では必要テーブルは自動初期化されます。この画面は再適用が必要な場合の補助機能です。</p>

    <?php if ($status !== '') : ?>
        <div class="admin-card">
            <p><?php echo e($status); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error !== '') : ?>
        <div class="admin-card">
            <p><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <form class="admin-card" method="post" action="<?php echo e(admin_url('db_init.php')); ?>">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <input type="hidden" name="action" value="init_db">
        <button type="submit">スキーマ再適用</button>
    </form>

    <?php if ($isDevEnvironment) : ?>
        <form class="admin-card" method="post" action="<?php echo e(admin_url('db_init.php')); ?>">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="reset_admin_password">
            <button type="submit">（開発用）adminパスを初期化</button>
        </form>
    <?php endif; ?>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
