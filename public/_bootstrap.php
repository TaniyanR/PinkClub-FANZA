<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/access_analytics.php';
require_once __DIR__ . '/../lib/scheduler.php';

/**
 * Run due API sync jobs after the current web response has been sent.
 *
 * This provides cron-less, access-triggered automatic updates: a normal site
 * visit becomes the timer tick, while scheduler locks/interval checks decide
 * whether an actual sync should run.
 */
function run_access_triggered_scheduler(): void
{
    if (PHP_SAPI === 'cli') {
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

run_access_triggered_scheduler();
