<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/admin_auth.php';

function admin_count_table(string $table): ?int
{
    try {
        $stmt = db()->query("SELECT COUNT(*) FROM {$table}");
        return $stmt === false ? null : (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

function admin_table_exists(string $table): bool
{
    try {
        $stmt = db()->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);
        return $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

$dbStatus = 'OK';
try {
    db();
} catch (Throwable $e) {
    $dbStatus = 'NG';
}

$itemsCount = admin_count_table('items');
$importedAt = null;
if ($itemsCount !== null) {
    try {
        $stmt = db()->query('SELECT MAX(updated_at) FROM items');
        $importedAt = $stmt !== false ? (string)$stmt->fetchColumn() : null;
    } catch (Throwable $e) {
        $importedAt = null;
    }
}

$cards = [
    ['label' => 'DB接続', 'value' => $dbStatus],
    ['label' => 'テーブル初期化', 'value' => admin_table_exists('items') ? '完了' : '未実施'],
    ['label' => 'API設定', 'value' => trim((string)config_get('dmm_api.api_id', '')) !== '' ? '設定済み' : '未設定'],
    ['label' => '作品件数', 'value' => $itemsCount === null ? '未取得' : number_format($itemsCount) . '件'],
    ['label' => '最終インポート', 'value' => $importedAt ?: '未実施'],
];

$pageTitle = 'ダッシュボード';
ob_start();
?>
<h1>管理ダッシュボード</h1>

<?php if (admin_is_default_password()) : ?>
    <div class="admin-card admin-note">
        <p>初期パスワードのままです。必要に応じて「設定 > パスワード変更」から変更してください（任意）。</p>
    </div>
<?php endif; ?>

<div class="admin-status-grid">
    <?php foreach ($cards as $card) : ?>
        <section class="admin-card admin-status-card">
            <strong><?php echo e((string)$card['label']); ?></strong>
            <p><?php echo e((string)$card['value']); ?></p>
        </section>
    <?php endforeach; ?>
</div>

<div class="admin-card">
    <h2>クイック導線</h2>
    <ul>
        <li><a href="<?php echo e(admin_url('db_init.php')); ?>">DB初期化へ</a></li>
        <li><a href="<?php echo e(admin_url('settings.php')); ?>">API設定へ</a></li>
        <li><a href="<?php echo e(admin_url('import_items.php')); ?>">インポート実行へ</a></li>
        <li><a href="<?php echo e(admin_url('change_password.php')); ?>">パスワード変更へ</a></li>
    </ul>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
