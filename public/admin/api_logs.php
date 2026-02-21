<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
header('Location: ' . admin_url('api_log.php'), true, 302);
exit;
