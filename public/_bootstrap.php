<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/access_analytics.php';
require_once __DIR__ . '/../lib/scheduler.php';
require_once __DIR__ . '/../lib/crawler_guard.php';

/**
 * Run due API sync jobs after the current web response has been sent.
 *
 * This provides cron-less, access-triggered automatic updates: a normal site
 * visit becomes the timer tick, while scheduler locks/interval checks decide
 * whether an actual sync should run.
 */
function run_access_triggered_scheduler(): void
{
    if (PHP_SAPI === 'cli' || !access_triggered_scheduler_should_run()) {
        return;
    }

    register_shutdown_function(static function (): void {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        try {
            maybe_run_scheduled_jobs();
        } catch (Throwable $e) {
            error_log('[scheduler] ' . $e->getMessage());
        }
    });
}

function access_triggered_scheduler_should_run(): bool
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return false;
    }

    if (pcf_crawler_guard_is_known_crawler((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        return false;
    }

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    foreach ([$scriptName, $requestUri] as $path) {
        if (preg_match('#/(admin|scripts)(/|$)#', $path) === 1) {
            return false;
        }
    }

    return true;
}

pcf_crawler_guard_check();
run_access_triggered_scheduler();
