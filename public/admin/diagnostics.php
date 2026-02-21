<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';
require_once __DIR__ . '/../../lib/local_config_writer.php';

function diag_mask(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '(未設定)';
    }

    $len = mb_strlen($trimmed);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }

    return mb_substr($trimmed, 0, 2) . str_repeat('*', max(1, $len - 4)) . mb_substr($trimmed, -2);
}

function diag_bool_badge(bool $ok): string
{
    return $ok ? 'OK' : 'NG';
}

$checks = [];
$tableChecks = [];
$dbName = '';
$dbError = '';
$adminCount = 0;
$currentAdmin = admin_current_user();
$adminColumns = [];
$lastErrorLog = 'なし';

try {
    $pdo = db();
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $checks['db_connection'] = true;
} catch (Throwable $exception) {
    $checks['db_connection'] = false;
    $dbError = app_is_development() ? $exception->getMessage() : 'DB接続に失敗しました。';
}

$requiredTables = [
    'admin_users',
    'settings',
    'site_settings',
    'items',
    'api_logs',
    'access_events',
];

if (($checks['db_connection'] ?? false) === true) {
    foreach ($requiredTables as $table) {
        $tableChecks[$table] = admin_table_exists($table);
    }

    $adminCountStmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
    $adminCount = (int)$adminCountStmt->fetchColumn();

    $columnsStmt = $pdo->query('SHOW COLUMNS FROM admin_users');
    $adminColumns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
}

$siteSettings = [
    'site_title' => site_title_setting(''),
    'site.base_url' => (string)(setting_get('site.base_url', '') ?? ''),
    'site.tagline' => (string)(setting_get('site.tagline', '') ?? ''),
    'show_tagline' => (string)(setting_get('show_tagline', '0') ?? '0'),
    'site.admin_email' => (string)(setting_get('site.admin_email', '') ?? ''),
];

$apiConfig = config_get('dmm_api', []);
if (!is_array($apiConfig)) {
    $apiConfig = [];
}
$apiChecks = [
    'api_id' => diag_mask((string)($apiConfig['api_id'] ?? '')),
    'affiliate_id' => diag_mask((string)($apiConfig['affiliate_id'] ?? '')),
    'site' => (string)($apiConfig['site'] ?? ''),
    'service' => (string)($apiConfig['service'] ?? ''),
    'floor' => (string)($apiConfig['floor'] ?? ''),
    'connect_timeout' => (string)($apiConfig['connect_timeout'] ?? ''),
    'timeout' => (string)($apiConfig['timeout'] ?? ''),
];

$uploadDir = dirname(__DIR__) . '/uploads/site_assets';
$uploadChecks = [
    'dir_exists' => is_dir($uploadDir),
    'dir_writable' => is_dir($uploadDir) ? is_writable($uploadDir) : false,
    'logo_exists' => is_file($uploadDir . '/logo.jpg') || is_file($uploadDir . '/logo.png') || is_file($uploadDir . '/logo.gif') || is_file($uploadDir . '/logo.webp'),
    'ogp_exists' => is_file($uploadDir . '/ogp.jpg') || is_file($uploadDir . '/ogp.png') || is_file($uploadDir . '/ogp.gif') || is_file($uploadDir . '/ogp.webp'),
];

$apiLogPath = dirname(__DIR__, 2) . '/storage/logs/api.log';
$logPath = dirname(__DIR__, 2) . '/storage/logs/php-error.log';
$lastApiLog = 'なし';
if (is_file($apiLogPath)) { $mtime = filemtime($apiLogPath); if ($mtime !== false) { $lastApiLog = date('Y-m-d H:i:s', $mtime);} }
if (is_file($logPath)) {
    $mtime = filemtime($logPath);
    if ($mtime !== false) {
        $lastErrorLog = date('Y-m-d H:i:s', $mtime);
    }
}

$pageTitle = '診断';
ob_start();
?>
<h1>簡易診断</h1>
<div class="admin-card">
    <h2>A. DB接続</h2>
    <ul>
        <li>PDO接続: <?php echo e(diag_bool_badge(($checks['db_connection'] ?? false) === true)); ?></li>
        <li>接続先DB名: <?php echo e($dbName !== '' ? $dbName : '(取得不可)'); ?></li>
        <?php if ($dbError !== '') : ?>
            <li>エラー: <?php echo e($dbError); ?></li>
        <?php endif; ?>
    </ul>
</div>

<div class="admin-card">
    <h2>B. 必須テーブル存在確認</h2>
    <ul>
        <?php foreach ($requiredTables as $table) : ?>
            <li><?php echo e($table); ?>: <?php echo e(diag_bool_badge((bool)($tableChecks[$table] ?? false))); ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="admin-card">
    <h2>C. 管理者ユーザー確認</h2>
    <ul>
        <li>admin_users 件数: <?php echo e((string)$adminCount); ?></li>
        <li>ログイン中ID: <?php echo e(is_array($currentAdmin) ? (string)($currentAdmin['id'] ?? '') : '(未ログイン)'); ?></li>
        <li>ログイン中username: <?php echo e(is_array($currentAdmin) ? (string)($currentAdmin['username'] ?? '') : '(未ログイン)'); ?></li>
        <li>password_hash カラム: <?php echo e(diag_bool_badge(in_array('password_hash', $adminColumns, true))); ?></li>
        <li>password(legacy) カラム: <?php echo e(diag_bool_badge(in_array('password', $adminColumns, true))); ?></li>
    </ul>
</div>

<div class="admin-card">
    <h2>D. 設定読込確認</h2>
    <ul>
        <?php foreach ($siteSettings as $key => $value) : ?>
            <li><?php echo e($key); ?>: <?php echo e($key === 'site.admin_email' ? diag_mask((string)$value) : (string)$value); ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="admin-card">
    <h2>E. API設定確認</h2>
    <ul>
        <?php foreach ($apiChecks as $key => $value) : ?>
            <li><?php echo e($key); ?>: <?php echo e($value !== '' ? $value : '(未設定)'); ?></li>
        <?php endforeach; ?>
        <li>保存先: config.local.php（ファイル保存）</li>
    </ul>
</div>

<div class="admin-card">
    <h2>F. アップロード先確認</h2>
    <ul>
        <li>ディレクトリ存在: <?php echo e(diag_bool_badge($uploadChecks['dir_exists'])); ?></li>
        <li>書き込み権限: <?php echo e(diag_bool_badge($uploadChecks['dir_writable'])); ?></li>
        <li>ロゴファイル有無: <?php echo e(diag_bool_badge($uploadChecks['logo_exists'])); ?></li>
        <li>OGPファイル有無: <?php echo e(diag_bool_badge($uploadChecks['ogp_exists'])); ?></li>
    </ul>
</div>

<div class="admin-card">
    <h2>ログ</h2>
    <ul>
        <li>APIログ: <?php echo e($apiLogPath); ?> / 最終更新: <?php echo e($lastApiLog); ?></li>
        <li>PHPエラーログ: <?php echo e($logPath); ?> / 最終更新: <?php echo e($lastErrorLog); ?></li>
        <li>設定保存先: サイト設定/デザイン設定はDB(site_settings)、API設定はconfig.local.php</li>
    </ul>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
