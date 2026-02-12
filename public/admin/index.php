<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/admin_auth.php';

function admin_table_map(PDO $pdo): array
{
    $tables = [];
    $stmt = $pdo->query('SHOW TABLES');
    if ($stmt === false) {
        return $tables;
    }

    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
        $name = strtolower((string)($row[0] ?? ''));
        if ($name !== '') {
            $tables[$name] = true;
        }
    }

    return $tables;
}

function admin_table_has_column(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        if ($stmt === false) {
            return false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (strtolower((string)($row['Field'] ?? '')) === strtolower($column)) {
                return true;
            }
        }
    } catch (Throwable $e) {
        admin_log_error('Failed to inspect table column', $e);
    }

    return false;
}

$dbStatus = 'NG';
$dbConnected = false;
$pdo = null;

try {
    $pdo = db();
    $dbConnected = true;
    $dbStatus = 'OK';
} catch (Throwable $e) {
    admin_log_error('Dashboard DB connection failed', $e);
}

$existingTables = [];
$requiredCandidates = ['items', 'actresses', 'genres', 'makers', 'series', 'api_logs', 'rss_sources', 'rss_items', 'mutual_links', 'access_events', 'page_views', 'pages'];
$requiredTables = [];
$missingTables = [];

if ($dbConnected && $pdo instanceof PDO) {
    try {
        $existingTables = admin_table_map($pdo);
        foreach ($requiredCandidates as $table) {
            if (isset($existingTables[$table])) {
                $requiredTables[] = $table;
            }
        }

        if ($requiredTables === []) {
            $requiredTables = ['items', 'pages'];
        }

        foreach ($requiredTables as $required) {
            if (!isset($existingTables[$required])) {
                $missingTables[] = $required;
            }
        }
    } catch (Throwable $e) {
        admin_log_error('Dashboard table check failed', $e);
    }
}

$tableStatus = '未実施';
if (!$dbConnected) {
    $tableStatus = '未実施';
} elseif ($missingTables === []) {
    $tableStatus = '実施';
} else {
    $tableStatus = '未実施（不足: ' . implode(', ', $missingTables) . '）';
}

$api = config_get('dmm_api', []);
$apiId = trim((string)($api['api_id'] ?? ''));
$affiliateId = trim((string)($api['affiliate_id'] ?? ''));
$apiStatus = ($apiId !== '' && $affiliateId !== '') ? '設定済' : '未設定';

$itemsCountLabel = '未取得';
if ($dbConnected && isset($existingTables['items']) && $pdo instanceof PDO) {
    try {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
        $itemsCountLabel = number_format($count) . '件';
    } catch (Throwable $e) {
        admin_log_error('Dashboard items count failed', $e);
    }
}

$lastImportLabel = '未実施';
if ($dbConnected && $pdo instanceof PDO) {
    try {
        if (isset($existingTables['api_logs']) && admin_table_has_column($pdo, 'api_logs', 'created_at')) {
            $stmt = $pdo->query("SELECT MAX(created_at) FROM api_logs WHERE status IN ('success', 'ok', 'SUCCESS') OR success = 1");
            $value = $stmt !== false ? (string)$stmt->fetchColumn() : '';
            if ($value !== '') {
                $lastImportLabel = $value;
            }
        }

        if ($lastImportLabel === '未実施' && isset($existingTables['items']) && admin_table_has_column($pdo, 'items', 'updated_at')) {
            $stmt = $pdo->query('SELECT MAX(updated_at) FROM items');
            $value = $stmt !== false ? (string)$stmt->fetchColumn() : '';
            if ($value !== '') {
                $lastImportLabel = $value;
            }
        }
    } catch (Throwable $e) {
        admin_log_error('Dashboard last import check failed', $e);
    }
}

$pageTitle = 'ダッシュボード';
ob_start();
?>
<h1>管理ダッシュボード</h1>

<?php if (admin_is_default_password()) : ?>
    <div class="admin-card admin-note">
        <p>初期パスワードのままです。必要に応じて「パスワード変更」から変更してください（任意）。</p>
    </div>
<?php endif; ?>

<div class="admin-status-grid">
    <section class="admin-card admin-status-card"><strong>DB接続</strong><p><?php echo e($dbStatus); ?></p></section>
    <section class="admin-card admin-status-card"><strong>テーブル初期化</strong><p><?php echo e($tableStatus); ?></p></section>
    <section class="admin-card admin-status-card"><strong>API設定</strong><p><?php echo e($apiStatus); ?></p><?php if ($apiStatus === '未設定') : ?><a href="<?php echo e(admin_url('settings.php')); ?>">設定する</a><?php endif; ?></section>
    <section class="admin-card admin-status-card"><strong>作品件数</strong><p><?php echo e($itemsCountLabel); ?></p></section>
    <section class="admin-card admin-status-card"><strong>最終インポート</strong><p><?php echo e($lastImportLabel); ?></p><?php if ($lastImportLabel === '未実施') : ?><a href="<?php echo e(admin_url('import_items.php')); ?>">インポートを実行</a><?php endif; ?></section>
</div>

<div class="admin-card">
    <h2>次にやること</h2>
    <p>
        <a class="button" href="<?php echo e(admin_url('db_init.php')); ?>">DB初期化</a>
        <a class="button" href="<?php echo e(admin_url('settings.php')); ?>">API設定</a>
        <a class="button" href="<?php echo e(admin_url('import_items.php')); ?>">インポート</a>
    </p>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
