<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/bootstrap.php';

$GLOBALS['__admin_trace'] = [];

function admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function admin_is_dev_environment(): bool
{
    return app_is_development();
}

app_bootstrap_configure_php_errors();

function admin_log_error(string $message, ?Throwable $exception = null): void
{
    app_log_error('[admin] ' . $message, $exception);
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
    @file_put_contents(app_log_file_path(), $line, FILE_APPEND);
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

app_register_error_handlers(static function (string $message, ?Throwable $exception = null): void {
    admin_trace_push('exception:handler');
    admin_log_trace($message);
    admin_render_error_page('管理画面エラー', $message, $exception);
    exit;
}, 'admin');

admin_trace_push('bootstrap:start');

try {
    require_once __DIR__ . '/../../lib/db.php';
    require_once __DIR__ . '/../../lib/admin_auth.php';
    require_once __DIR__ . '/../../lib/csrf.php';
    require_once __DIR__ . '/../../lib/url.php';
    require_once __DIR__ . '/../../lib/scheduler.php';
    require_once __DIR__ . '/../partials/_helpers.php';
} catch (Throwable $exception) {
    admin_log_error('Require failed in admin bootstrap', $exception);
    admin_render_error_page('管理画面エラー', '管理画面の初期化に失敗しました。', $exception);
    exit;
}

admin_trace_push('bootstrap:requires_ok');
$GLOBALS['admin_error_layout_enabled'] = true;
admin_trace_push('bootstrap:layout_enabled');
