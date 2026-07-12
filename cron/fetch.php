<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/cron_guard.php';
require_once __DIR__ . '/../lib/scheduler.php';

cron_require_authorized_web();
$exit = cron_with_file_lock('fetch', static function (): int {
    maybe_run_scheduled_jobs();
    echo '[' . date('Y-m-d H:i:s') . "] fetch cron executed\n";
    return 0;
});
if (PHP_SAPI === 'cli') { exit($exit); }
