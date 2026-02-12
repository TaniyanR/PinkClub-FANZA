<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../scripts/init_db.php';


$status = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        $error = '不正なリクエストです。';
    } else {
        try {
            $result = init_db();
            $status = sprintf('DB初期化が完了しました。（%s使用: %dステートメント）', $result['source'], $result['count']);
        } catch (Throwable $e) {
            error_log('db_init failed: ' . $e->getMessage());
            $error = 'DB初期化に失敗しました。ログを確認してください。';
        }
    }
}

$pageTitle = 'DB初期化';
ob_start();
?>
    <h1>DB初期化</h1>

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
        <button type="submit">DB初期化</button>
    </form>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
