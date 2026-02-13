<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/scheduler.php';

function main(): int
{
    try {
        maybe_run_scheduled_jobs();
        echo '[' . date('Y-m-d H:i:s') . "] maybe_run_scheduled_jobs() executed\n";
        return 0;
    } catch (Throwable $e) {
        log_message('[auto_import] ' . $e->getMessage());
        fwrite(STDERR, $e->getMessage() . "\n");
        return 1;
    }
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(main());
}
