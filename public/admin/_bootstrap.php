<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

$script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if (!in_array($script, ['login.php', 'logout.php'], true)) {
    admin_require_login();
}
