<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

function health_is_dev(): bool
{
    $env = strtolower((string)config_get('app.env', ''));
    if ($env !== '' && in_array($env, ['dev', 'development', 'local', 'staging'], true)) {
        return true;
    }

    return filter_var((string)config_get('app.debug', false), FILTER_VALIDATE_BOOL) === true;
}

function health_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

$appEnv = (string)config_get('app.env', '');
$logPath = __DIR__ . '/../logs/app.log';
$logDir = dirname($logPath);
$logWritable = false;

if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
    $probe = sprintf("[%s] health check\n", date('Y-m-d H:i:s'));
    $logWritable = @file_put_contents($logPath, $probe, FILE_APPEND) !== false;
}

$dbOk = false;
$dbDetail = '';

try {
    db()->query('SELECT 1');
    $dbOk = true;
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
}

$isDev = health_is_dev();
?><!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Check</title>
    <style>
        body { margin: 2rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1f2937; }
        table { border-collapse: collapse; width: 100%; max-width: 760px; }
        th, td { border: 1px solid #d1d5db; padding: 10px; text-align: left; }
        th { background: #f3f4f6; width: 280px; }
        .ok { color: #047857; font-weight: 600; }
        .ng { color: #b91c1c; font-weight: 600; }
        pre { background: #f9fafb; border: 1px solid #e5e7eb; padding: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
<h1>PinkClub-FANZA Health Check</h1>
<table>
    <tr>
        <th>PHPバージョン</th>
        <td><?php echo health_escape(PHP_VERSION); ?></td>
    </tr>
    <tr>
        <th>app.env 判定</th>
        <td><?php echo health_escape($appEnv !== '' ? $appEnv : '(unset)'); ?> / <?php echo $isDev ? 'development' : 'production'; ?></td>
    </tr>
    <tr>
        <th>logs/app.log 追記</th>
        <td class="<?php echo $logWritable ? 'ok' : 'ng'; ?>"><?php echo $logWritable ? 'OK' : 'NG'; ?></td>
    </tr>
    <tr>
        <th>DB接続</th>
        <td class="<?php echo $dbOk ? 'ok' : 'ng'; ?>"><?php echo $dbOk ? 'OK' : 'NG'; ?></td>
    </tr>
</table>
<?php if ($isDev && !$dbOk && $dbDetail !== ''): ?>
    <h2>DBエラー詳細（devのみ）</h2>
    <pre><?php echo health_escape($dbDetail); ?></pre>
<?php endif; ?>
</body>
</html>
