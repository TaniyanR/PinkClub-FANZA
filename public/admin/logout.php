<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../lib/admin_auth_v2.php';

admin_v2_logout();
app_redirect(login_url());
