<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/site_settings.php';
require_once __DIR__ . '/../../lib/dmm_api.php';
require_once __DIR__ . '/../../lib/fanza_api_config.php';

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
$apiConnectionStatus = 'NG';
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

if ($dbConnected && $pdo instanceof PDO) {
    try {
        $existingTables = admin_table_map($pdo);
    } catch (Throwable $e) {
        admin_log_error('Dashboard table map load failed', $e);
    }
}

$tableStatus = $dbConnected ? '自動初期化済み' : 'DB接続エラーのため確認不可';

$apiConfig = fanza_normalize_api_config(config_get('dmm_api', []));
$apiKey = trim((string)(is_array($apiConfig) ? ($apiConfig['api_id'] ?? '') : ''));
$affiliateId = trim((string)(is_array($apiConfig) ? ($apiConfig['affiliate_id'] ?? '') : ''));
$apiStatus = ($apiKey !== '' && $affiliateId !== '') ? '設定済' : '未設定';


try {
        $apiConnectionStatus = 'NG';
        if ($apiKey !== '' && $affiliateId !== '') {
            $params = [
                'api_id' => $apiKey,
                'affiliate_id' => $affiliateId,
                'site' => 'FANZA',
                'service' => (string)($apiConfig['service'] ?? 'digital'),
                'floor' => (string)($apiConfig['floor'] ?? 'videoa'),
                'hits' => 1,
                'sort' => 'rank',
                'output' => 'json',
            ];
            $apiResponse = dmm_api_request('ItemList', $params);
            $apiConnectionStatus = (!empty($apiResponse['ok']) || !empty($apiResponse['is_cached'])) ? 'OK' : 'NG';
        }
    } catch (Throwable $e) {
        admin_log_error('Dashboard API connection check failed', $e);
        $apiConnectionStatus = 'NG';
    }

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


$scheduleWarning = '';
if ($dbConnected && $pdo instanceof PDO) {
    try {
        $sch = $pdo->query('SELECT fail_count,last_error FROM api_schedules ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if (is_array($sch) && (int)($sch['fail_count'] ?? 0) >= 5) {
            $scheduleWarning = '内部タイマーが連続失敗しています: ' . (string)($sch['last_error'] ?? '');
        }
    } catch (Throwable $e) {
        admin_log_error('Dashboard schedule warning check failed', $e);
    }
}

$pageTitle = 'ダッシュボード';
ob_start();
?>
<h1>管理ダッシュボード</h1>

<?php $currentAdmin = admin_current_user(); ?>
<?php if (is_array($currentAdmin) && admin_is_default_password($currentAdmin)) : ?>
    <div class="admin-card admin-note">
        <p>初期パスワードのままです。必要に応じて「パスワード変更」から変更してください（任意）。</p>
    </div>
<?php endif; ?>

<?php if ($scheduleWarning !== '') : ?><div class="admin-card admin-note"><p><?php echo e($scheduleWarning); ?></p></div><?php endif; ?>

<div class="admin-status-grid">
    <section class="admin-card admin-status-card"><strong>API接続</strong><p><?php echo e($apiConnectionStatus); ?></p></section>
    <section class="admin-card admin-status-card"><strong>DB接続</strong><p><?php echo e($dbStatus); ?></p></section>
    <section class="admin-card admin-status-card"><strong>テーブル状態</strong><p><?php echo e($tableStatus); ?></p></section>
    <section class="admin-card admin-status-card"><strong>API設定</strong><p><?php echo e($apiStatus); ?></p><?php if ($apiStatus === '未設定') : ?><a href="<?php echo e(admin_url('settings.php')); ?>">設定する</a><?php endif; ?></section>
    <section class="admin-card admin-status-card"><strong>作品件数</strong><p><?php echo e($itemsCountLabel); ?></p></section>
    <section class="admin-card admin-status-card"><strong>最終インポート</strong><p><?php echo e($lastImportLabel); ?></p></section>
</div>

<div class="admin-card">
    <h2>次にやること</h2>
    <p>
        <a class="button" href="<?php echo e(admin_url('settings.php')); ?>">API設定</a>
    </p>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
