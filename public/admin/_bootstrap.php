<?php

declare(strict_types=1);

// 開発時のみ有効化: 白画面(Fatal)切り分け用
ini_set('display_errors', '1');
error_reporting(E_ALL);

$GLOBALS['__admin_trace'] = [];

function admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function admin_bootstrap_is_dev_environment(): bool
{
    $appEnv = getenv('APP_ENV');
    if (is_string($appEnv) && $appEnv !== '' && in_array(strtolower($appEnv), ['dev', 'development', 'local'], true)) {
        return true;
    }

    $appDebug = getenv('APP_DEBUG');
    if (is_string($appDebug) && in_array(strtolower($appDebug), ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    return filter_var((string)ini_get('display_errors'), FILTER_VALIDATE_BOOL) === true;
}

function admin_is_dev_environment(): bool
{
    if (function_exists('config_get')) {
        $configEnv = config_get('app.env', null);
        $env = is_string($configEnv) && $configEnv !== '' ? $configEnv : (getenv('APP_ENV') ?: '');
        if (is_string($env) && in_array(strtolower($env), ['dev', 'development', 'local'], true)) {
            return true;
        }
    }

    return admin_bootstrap_is_dev_environment();
}

function admin_log_error(string $message, ?Throwable $exception = null): void
{
    if ($exception instanceof Throwable) {
        $message .= sprintf(
            ' | %s: %s @ %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
    }

    $line = '[admin] ' . $message;

    try {
        if (function_exists('log_message')) {
            log_message($line);
            return;
        }
    } catch (Throwable) {
    }

    error_log($line);
}

function admin_trace_push(string $stage): void
{
    $trace = $GLOBALS['__admin_trace'] ?? [];
    if (!is_array($trace)) {
        $trace = [];
    }

    $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
    $trace[] = [
        't' => microtime(true),
        'stage' => $stage,
        'file' => (string)($caller['file'] ?? __FILE__),
    ];
    $GLOBALS['__admin_trace'] = $trace;
}

function admin_trace_lines(): array
{
    $trace = $GLOBALS['__admin_trace'] ?? [];
    if (!is_array($trace) || $trace === []) {
        return ['(trace empty)'];
    }

    $lines = [];
    foreach ($trace as $idx => $row) {
        $time = isset($row['t']) ? (float)$row['t'] : microtime(true);
        $stage = isset($row['stage']) ? (string)$row['stage'] : '(unknown)';
        $file = isset($row['file']) ? (string)$row['file'] : '(unknown)';
        $lines[] = sprintf('#%d %.6f %s @ %s', $idx + 1, $time, $stage, $file);
    }

    return $lines;
}

function admin_log_trace(string $reason): void
{
    $line = '[admin-trace] ' . $reason . PHP_EOL . implode(PHP_EOL, admin_trace_lines()) . PHP_EOL;
    @file_put_contents(dirname(__DIR__, 2) . '/logs/app.log', $line, FILE_APPEND);
}

function admin_trace_html(): string
{
    return '<details><summary>Trace</summary><pre>' . admin_h(implode("\n", admin_trace_lines())) . '</pre></details>';
}

function admin_render_plain_error_page(string $title, string $message, ?Throwable $exception = null): void
{
    if (headers_sent() === false) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $isDev = admin_is_dev_environment();

    echo '<!doctype html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . admin_h($title) . '</title>';
    echo '<style>body{margin:0;background:#f6f7f7;color:#1d2327;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{max-width:760px;margin:56px auto;padding:0 16px}.card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px}h1{margin-top:0;font-size:24px}pre{white-space:pre-wrap;background:#f0f2f5;border-radius:6px;padding:12px;overflow:auto}</style>';
    echo '</head><body><div class="wrap"><section class="card">';
    echo '<h1>' . admin_h($title) . '</h1><p>' . admin_h($message) . '</p>';

    if ($isDev && $exception !== null) {
        echo '<hr><p><strong>' . admin_h(get_class($exception)) . '</strong>: ' . admin_h($exception->getMessage()) . '</p>';
        echo '<p>' . admin_h($exception->getFile()) . ':' . admin_h((string)$exception->getLine()) . '</p>';
        echo '<pre>' . admin_h($exception->getTraceAsString()) . '</pre>';
    }

    if ($isDev) {
        echo admin_trace_html();
    }

    echo '</section></div></body></html>';
}

function admin_render_error_page(string $title, string $message, ?Throwable $exception = null): void
{
    $layoutEnabled = (bool)($GLOBALS['admin_error_layout_enabled'] ?? false);
    if (!$layoutEnabled) {
        admin_render_plain_error_page($title, $message, $exception);
        return;
    }

    try {
        if (headers_sent() === false) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $isDev = admin_is_dev_environment();
        $pageTitle = $title;

        ob_start();
        ?>
        <h1><?php echo admin_h($title); ?></h1>
        <div class="admin-card">
            <p><?php echo admin_h($message); ?></p>
            <?php if ($isDev && $exception !== null) : ?>
                <hr>
                <p><strong><?php echo admin_h(get_class($exception)); ?></strong>: <?php echo admin_h($exception->getMessage()); ?></p>
                <p><?php echo admin_h($exception->getFile()); ?>:<?php echo admin_h((string)$exception->getLine()); ?></p>
                <pre><?php echo admin_h($exception->getTraceAsString()); ?></pre>
            <?php endif; ?>
            <?php if ($isDev) : ?>
                <?php echo admin_trace_html(); ?>
            <?php endif; ?>
        </div>
        <?php
        $content = (string)ob_get_clean();
        include __DIR__ . '/../partials/admin_layout.php';
    } catch (Throwable $layoutException) {
        admin_log_error('admin layout render failed', $layoutException);
        admin_render_plain_error_page($title, $message, $exception);
    }
}

set_exception_handler(static function (Throwable $exception): void {
    admin_trace_push('exception:handler');
    admin_log_error('Unhandled exception', $exception);
    admin_log_trace('Unhandled exception');

    $publicMessage = admin_is_dev_environment()
        ? '管理画面で例外が発生しました。'
        : 'エラーが発生しました。時間をおいて再度お試しください。';

    admin_render_error_page('管理画面エラー', $publicMessage, $exception);
    exit;
});

register_shutdown_function(static function (): void {
    $lastError = error_get_last();
    $fatal = is_array($lastError) && in_array((int)$lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    if (!$fatal) {
        return;
    }

    $exception = new ErrorException(
        (string)$lastError['message'],
        0,
        (int)$lastError['type'],
        (string)$lastError['file'],
        (int)$lastError['line']
    );

    admin_log_error('Shutdown fatal error', $exception);
    admin_log_trace('Shutdown fatal error');

    if (!headers_sent()) {
        admin_render_error_page('管理画面エラー', 'システムエラーが発生しました。管理者へお問い合わせください。', $exception);
    }
});

admin_trace_push('bootstrap:start');

try {
    require_once __DIR__ . '/../../lib/config.php';
    require_once __DIR__ . '/../../lib/db.php';
    require_once __DIR__ . '/../../lib/admin_auth.php';
    require_once __DIR__ . '/../../lib/admin_auth_simple.php';
    require_once __DIR__ . '/../../lib/csrf.php';
    require_once __DIR__ . '/../../lib/url.php';
    require_once __DIR__ . '/../../lib/scheduler.php';
    require_once __DIR__ . '/../partials/_helpers.php';
} catch (Throwable $exception) {
    admin_log_error('Require failed in admin bootstrap', $exception);
    admin_render_error_page('管理画面エラー', '管理画面の初期化に失敗しました。', $exception);
    exit;
}

$GLOBALS['admin_error_layout_enabled'] = true;

if (headers_sent() === false) {
    header('X-Robots-Tag: noindex, nofollow');
}

admin_simple_session_start();
admin_trace_push('bootstrap:session:start');

$GLOBALS['admin_db_ready'] = false;
try {
    db();
    $GLOBALS['admin_db_ready'] = true;
    admin_trace_push('bootstrap:db:ready');
} catch (Throwable $exception) {
    admin_trace_push('bootstrap:db:failed');
    admin_log_error('DB initialization failed (non-blocking for minimal login)', $exception);
}

$script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isLogin = $script === 'login.php';
$isLogout = $script === 'logout.php';
$isEmailVerify = $script === 'verify_email.php';

if (!$isLogin && !$isLogout && !$isEmailVerify) {
    admin_trace_push('bootstrap:auth:check');
    admin_simple_require_login();
}

if (($GLOBALS['admin_db_ready'] ?? false) === true) {
    try {
        maybe_run_scheduled_jobs();
    } catch (Throwable $exception) {
        admin_log_error('Scheduled job failed (non-blocking)', $exception);
    }
}

admin_trace_push('bootstrap:end');
