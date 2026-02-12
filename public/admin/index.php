<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

$itemCount = null;
try {
    $stmt = db()->query('SELECT COUNT(*) FROM items');
    $count = $stmt !== false ? $stmt->fetchColumn() : null;
    $itemCount = is_numeric($count) ? (int)$count : null;
} catch (Throwable $e) {
    $itemCount = null;
}

$pageTitle = '管理ダッシュボード';
ob_start();
?>
    <h1>管理ダッシュボード</h1>

    <div class="admin-card">
        <ul>
            <li><a href="<?php echo e(admin_url('settings.php')); ?>">管理設定</a></li>
            <li><a href="<?php echo e(admin_url('db_init.php')); ?>">DB初期化</a></li>
            <li><a href="<?php echo e(admin_url('import_items.php')); ?>">インポート</a></li>
            <li><a href="<?php echo e(admin_url('change_password.php')); ?>">パスワード変更</a></li>
        </ul>
        <?php if ($itemCount !== null) : ?>
            <p>登録済み作品数: <?php echo e((string)$itemCount); ?> 件</p>
        <?php endif; ?>
    </div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
