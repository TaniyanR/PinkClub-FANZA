<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/url.php';

admin_session_start();

$script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isLogin = $script === 'login.php';
$isLogout = $script === 'logout.php';
$isChangePassword = $script === 'change_password.php';

if ($isLogin || $isLogout) {
    return;
}

if ($isChangePassword) {
    admin_require_login();
    return;
}

admin_require_login();
