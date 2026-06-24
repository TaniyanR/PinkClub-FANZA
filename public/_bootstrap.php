<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/access_analytics.php';
require_once __DIR__ . '/../lib/scheduler.php';
register_shutdown_function(static function () {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    try {
        maybe_run_scheduled_jobs();
    } catch (Throwable $e) {
        error_log('[scheduler] ' . $e->getMessage());
    }
});
