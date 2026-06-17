<?php

declare(strict_types=1);

$publicBootstrapPath = __DIR__ . '/../public/_bootstrap.php';

if (is_file($publicBootstrapPath)) {
    require_once $publicBootstrapPath;
} else {
    require_once __DIR__ . '/../lib/bootstrap.php';
    require_once __DIR__ . '/../lib/access_analytics.php';
    analytics_track_request();
}
