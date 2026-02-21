<?php

declare(strict_types=1);

function app_bootstrap_detect_development(): bool
{
    if (function_exists('config_get')) {
        $appEnv = strtolower((string)config_get('app.env', ''));
        if ($appEnv !== '' && in_array($appEnv, ['dev', 'development', 'local', 'staging'], true)) {
            return true;
        }

        $debug = config_get('app.debug', null);
        if (is_bool($debug)) {
            return $debug;
        }
    }

    $env = strtolower((string)(getenv('APP_ENV') ?: ''));
    if ($env !== '' && in_array($env, ['dev', 'development', 'local', 'staging'], true)) {
        return true;
    }

    $appDebug = strtolower((string)(getenv('APP_DEBUG') ?: ''));
    if (in_array($appDebug, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    return false;
}

function app_is_development(): bool
{
    return app_bootstrap_detect_development();
}

function app_log_file_path(): string
{
    $dir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir . '/php-error.log';
}

function app_bootstrap_configure_php_errors(): void
{
    ini_set('log_errors', '1');
    ini_set('error_log', app_log_file_path());

    if (app_is_development()) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
        return;
    }

    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

function app_log_error(string $message, ?Throwable $exception = null): void
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '-');
    $file = '-';
    $lineNo = 0;
    $errorType = 'ERROR';

    if ($exception instanceof Throwable) {
        $file = $exception->getFile();
        $lineNo = $exception->getLine();
        $errorType = $exception instanceof ErrorException
            ? 'PHP_' . (string)$exception->getSeverity()
            : get_class($exception);
        $message .= ' | ' . $exception->getMessage();
    }

    $logLine = sprintf(
        "[%s] [%s] %s | file=%s line=%d REQUEST_URI=%s\n",
        date('Y-m-d H:i:s'),
        $errorType,
        $message,
        $file,
        $lineNo,
        $uri
    );

    @file_put_contents(app_log_file_path(), $logLine, FILE_APPEND);
    error_log(trim($logLine));
}

function app_redirect(string $path): never
{
    $target = trim(str_replace(["\r", "\n"], '', $path));
    if ($target === '') {
        $target = '/';
    }

    if (function_exists('url')) {
        $target = url($target);
    } elseif (!preg_match('#^https?://#i', $target)) {
        if ($target[0] !== '/') {
            $target = '/' . ltrim($target, '/');
        }
        if (function_exists('base_url')) {
            $target = rtrim(base_url(), '/') . $target;
        }
    }

    header('Location: ' . $target);
    exit;
}


function app_register_error_handlers(callable $renderFatal, string $context = 'app'): void
{
    set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $exception) use ($renderFatal, $context): void {
        try {
            app_log_error('Unhandled exception [' . $context . ']', $exception);
        } catch (Throwable) {
        }

        $renderFatal('予期しないエラーが発生しました。', $exception);
    });

    register_shutdown_function(static function () use ($renderFatal, $context): void {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }

        $exception = new ErrorException(
            (string)($error['message'] ?? 'fatal error'),
            0,
            (int)($error['type'] ?? E_ERROR),
            (string)($error['file'] ?? __FILE__),
            (int)($error['line'] ?? 0)
        );

        try {
            app_log_error('Shutdown fatal error [' . $context . ']', $exception);
        } catch (Throwable) {
        }

        $renderFatal('致命的なエラーが発生しました。', $exception);
    });
}
