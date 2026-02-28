<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
require_once __DIR__ . '/../lib/scheduler.php';

auth_require_admin();
header('Content-Type: application/json; charset=utf-8');

echo json_encode(scheduler_tick(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
