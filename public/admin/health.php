<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$appEnv = (string)config_get('app.env', 'prod');
if ($appEnv === '') {
    $appEnv = 'prod';
}
$isDev = in_array(strtolower($appEnv), ['dev', 'development', 'local'], true);

$logStatus = 'NG';
$logDetail = '';
$logPath = __DIR__ . '/../../logs/app.log';
$logDir = dirname($logPath);
if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
    $probe = '[health] probe ' . date('Y-m-d H:i:s') . PHP_EOL;
    if (@file_put_contents($logPath, $probe, FILE_APPEND) !== false) {
        $logStatus = 'OK';
    } else {
        $logDetail = 'logs/app.log に追記できません';
    }
} else {
    $logDetail = 'logs ディレクトリを作成できません';
}

$dbStatus = 'NG';
$dbDetail = '';
$pdo = null;
try {
    $pdo = db();
    $dbStatus = 'OK';
} catch (Throwable $e) {
    $dbDetail = $isDev ? $e->getMessage() : 'DB接続に失敗しました';
}

$tableStatus = 'NG';
$tableDetail = '';
$requiredTables = ['admin_users', 'api_schedules', 'site_settings', 'items', 'pages'];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query('SHOW TABLES');
        $found = [];
        if ($stmt !== false) {
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
                $name = strtolower((string)($row[0] ?? ''));
                if ($name !== '') {
                    $found[$name] = true;
                }
            }
        }

        $missing = [];
        foreach ($requiredTables as $table) {
            if (!isset($found[strtolower($table)])) {
                $missing[] = $table;
            }
        }

        if ($missing === []) {
            $tableStatus = 'OK';
        } else {
            $tableDetail = '不足: ' . implode(', ', $missing);
        }
    } catch (Throwable $e) {
        $tableDetail = $isDev ? $e->getMessage() : 'テーブル確認に失敗しました';
    }
}

$fatalSummary = '';
if (is_file($logPath) && is_readable($logPath)) {
    $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = (string)$lines[$i];
            if (stripos($line, 'fatal') !== false || stripos($line, 'Unhandled exception') !== false || stripos($line, 'Shutdown fatal error') !== false) {
                $fatalSummary = $line;
                break;
            }
        }
    }
}

$pageTitle = '管理ヘルスチェック';
ob_start();
?>
<h1>管理ヘルスチェック</h1>
<div class="admin-status-grid">
    <section class="admin-card admin-status-card">
        <strong>app.env</strong>
        <p><?php echo e($appEnv); ?></p>
    </section>
    <section class="admin-card admin-status-card">
        <strong>logs/app.log 追記</strong>
        <p><?php echo e($logStatus); ?></p>
        <?php if ($logDetail !== '') : ?><p><?php echo e($logDetail); ?></p><?php endif; ?>
    </section>
    <section class="admin-card admin-status-card">
        <strong>DB接続</strong>
        <p><?php echo e($dbStatus); ?></p>
        <?php if ($dbDetail !== '') : ?><p><?php echo e($dbDetail); ?></p><?php endif; ?>
    </section>
    <section class="admin-card admin-status-card">
        <strong>主要テーブル</strong>
        <p><?php echo e($tableStatus); ?></p>
        <?php if ($tableDetail !== '') : ?><p><?php echo e($tableDetail); ?></p><?php endif; ?>
    </section>
</div>

<div class="admin-card">
    <h2>直近の致命エラー</h2>
    <?php if ($fatalSummary === '') : ?>
        <p>記録は見つかりませんでした。</p>
    <?php elseif ($isDev) : ?>
        <pre><?php echo e($fatalSummary); ?></pre>
    <?php else : ?>
        <p>直近で致命エラーを検知しています。詳細はログを確認してください。</p>
    <?php endif; ?>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
