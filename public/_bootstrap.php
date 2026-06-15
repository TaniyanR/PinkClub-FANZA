<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/access_analytics.php';
analytics_track_request();

$scriptFile = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
if ($scriptFile !== '' && str_starts_with($scriptFile, __DIR__ . DIRECTORY_SEPARATOR) && settings_bool('item_sync_enabled', false)) {
    require_once __DIR__ . '/../lib/scheduler.php';
    try {
        maybe_run_scheduled_jobs();
    } catch (Throwable $e) {
        error_log('[public_scheduler] ' . $e->getMessage());
    }
}
