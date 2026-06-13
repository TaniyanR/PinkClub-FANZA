<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/access_analytics.php';
analytics_track_request();

try {
    $autoSyncEnabled = settings_bool('item_sync_enabled', false);
} catch (Throwable) {
    $autoSyncEnabled = false;
}

if ($autoSyncEnabled) {
    register_shutdown_function(static function (): void {
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (in_array($script, ['timer_tick.php', 'scheduler_tick.php'], true)) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        try {
            require_once __DIR__ . '/../lib/scheduler.php';
            maybe_run_scheduled_jobs();
        } catch (Throwable $e) {
            error_log('[auto_scheduler] ' . $e->getMessage());
        }
    });
}
