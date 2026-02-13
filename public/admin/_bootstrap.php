<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/url.php';
require_once __DIR__ . '/../../lib/scheduler.php';
require_once __DIR__ . '/../partials/_helpers.php';

function admin_is_dev_environment(): bool
{
    $configEnv = config_get('app.env', null);
    $env = is_string($configEnv) && $configEnv !== '' ? $configEnv : (getenv('APP_ENV') ?: '');

    if (is_string($env) && in_array(strtolower($env), ['dev', 'development', 'local'], true)) {
        return true;
    }

    return filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOL) === true;
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

    log_message('[admin] ' . $message);
}

function admin_render_error_page(string $title, string $message, ?Throwable $exception = null): void
{
    if (headers_sent() === false) {
        http_response_code(500);
    }

    $isDev = admin_is_dev_environment();
    $pageTitle = $title;

    ob_start();
    ?>
    <h1><?php echo e($title); ?></h1>
    <div class="admin-card">
        <p><?php echo e($message); ?></p>

        <?php if ($isDev && $exception !== null) : ?>
            <hr>
            <p><strong><?php echo e(get_class($exception)); ?></strong>: <?php echo e($exception->getMessage()); ?></p>
            <p><?php echo e($exception->getFile()); ?>:<?php echo e((string)$exception->getLine()); ?></p>
            <pre><?php echo e($exception->getTraceAsString()); ?></pre>
        <?php endif; ?>
    </div>
    <?php
    $content = (string)ob_get_clean();
    include __DIR__ . '/../partials/admin_layout.php';
}

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    admin_log_error('Unhandled exception', $exception);

    $publicMessage = admin_is_dev_environment()
        ? '管理画面で例外が発生しました。'
        : 'エラーが発生しました。時間をおいて再度お試しください。';

    admin_render_error_page('管理画面エラー', $publicMessage, $exception);
    exit;
});

register_shutdown_function(static function (): void {
    $lastError = error_get_last();
    if (!is_array($lastError)) {
        return;
    }

    if (!in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
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

    $publicMessage = admin_is_dev_environment()
        ? '管理画面で致命的エラーが発生しました。'
        : 'システムエラーが発生しました。管理者へお問い合わせください。';

    admin_render_error_page('管理画面エラー', $publicMessage, $exception);
});

admin_session_start();

$script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isLogin = $script === 'login.php';
$isLogout = $script === 'logout.php';
$isEmailVerify = $script === 'verify_email.php';

if (!$isLogin && !$isLogout && !$isEmailVerify) {
    admin_require_login();
}

maybe_run_scheduled_jobs();
