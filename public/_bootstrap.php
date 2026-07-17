<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/access_analytics.php';
require_once __DIR__ . '/../lib/crawler_guard.php';
require_once __DIR__ . '/../lib/public_page_cache.php';

pcf_crawler_guard_check();

$publicScriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$publicPageCacheTtl = in_array($publicScriptName, ['actresses.php', 'actress.php'], true) ? 600 : 120;
pcf_public_page_cache_start($publicPageCacheTtl);
