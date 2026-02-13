<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/scheduler.php';
require_once __DIR__ . '/partials/_helpers.php';

function app_is_development(): bool
{
    $appEnv = strtolower((string)config_get('app.env', ''));
    if ($appEnv !== '' && in_array($appEnv, ['dev', 'development', 'local', 'staging'], true)) {
        return true;
    }

    $debug = config_get('app.debug', null);
    if (is_bool($debug)) {
        return $debug;
    }

    $envDebug = getenv('APP_DEBUG');
    if (is_string($envDebug) && in_array(strtolower($envDebug), ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    return false;
}

function render_fatal_error_page(string $message, ?Throwable $exception = null): never
{
    $isDevelopment = app_is_development();
    http_response_code(500);

    $detail = $isDevelopment && $exception !== null
        ? $exception->getMessage() . "\n\n" . $exception->getTraceAsString()
        : '';

    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>エラーが発生しました</title>
    <style>
        body { margin: 0; background: #f6f7f7; color: #1d2327; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .wrap { max-width: 760px; margin: 56px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; }
        h1 { margin-top: 0; font-size: 24px; }
        pre { white-space: pre-wrap; background: #f0f2f5; border-radius: 6px; padding: 12px; overflow: auto; }
    </style>
</head>
<body>
<div class="wrap">
    <section class="card">
        <h1>エラーが発生しました</h1>
        <p><?php echo e($message); ?></p>
        <?php if ($detail !== '') : ?>
            <pre><?php echo e($detail); ?></pre>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
<?php
    exit;
}

function render_setup_needed(?Throwable $exception = null): never
{
    $isDevelopment = app_is_development();
    http_response_code(500);

    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>セットアップが必要です</title>
    <style>
        body { margin: 0; background: #f6f7f7; color: #1d2327; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .wrap { max-width: 760px; margin: 56px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px; }
        h1 { margin-top: 0; font-size: 24px; }
        ul { padding-left: 18px; }
        pre { white-space: pre-wrap; background: #f0f2f5; border-radius: 6px; padding: 12px; overflow: auto; }
    </style>
</head>
<body>
<div class="wrap">
    <section class="card">
        <h1>PinkClub-FANZA のセットアップが必要です</h1>
        <p>データベース接続または初期化に失敗したため、フロント画面を表示できませんでした。</p>
        <ul>
            <li><code>config.local.php</code> にDB設定を追加してください。</li>
            <li><a href="<?php echo e(base_url() . '/admin/db_init.php'); ?>">/public/admin/db_init.php</a> でテーブルを作成してください。</li>
            <li><a href="<?php echo e(base_url() . '/admin/settings.php'); ?>">/public/admin/settings.php</a> でAPI設定を確認してください。</li>
            <li>管理ログインは <a href="<?php echo e(base_url() . '/login0718.php'); ?>">/public/login0718.php</a> です。</li>
        </ul>
        <?php if ($isDevelopment && $exception !== null) : ?>
            <h2>開発向けエラー詳細</h2>
            <pre><?php echo e($exception->getMessage()); ?></pre>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
<?php
    exit;
}

error_reporting(E_ALL);
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

ini_set('display_errors', app_is_development() ? '1' : '0');

set_exception_handler(static function (Throwable $e): void {
    log_message('[front_exception] ' . $e->getMessage());
    render_fatal_error_page('予期しないエラーが発生しました。', $e);
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    $message = sprintf('%s in %s:%d', (string)$error['message'], (string)$error['file'], (int)$error['line']);
    log_message('[front_shutdown] ' . $message);
    render_fatal_error_page('致命的なエラーが発生しました。', new RuntimeException($message));
});

try {
    db();
} catch (Throwable $e) {
    log_message('[front_setup_needed] ' . $e->getMessage());
    render_setup_needed($e);
}

maybe_run_scheduled_jobs();


if (isset($_GET['from']) && preg_match('/^\d+$/', (string)$_GET['from']) === 1) {
    try {
        db()->prepare('INSERT INTO access_events(event_type,event_at,path,referrer,link_id,ip_hash) VALUES("in",NOW(),:path,:ref,:link_id,:ip_hash)')
            ->execute([
                ':path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
                ':ref' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
                ':link_id' => (int)$_GET['from'],
                ':ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
            ]);
    } catch (Throwable $e) {
        log_message('[track_in] ' . $e->getMessage());
    }
}
