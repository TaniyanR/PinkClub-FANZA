<?php

declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_logout();
app_redirect(login_url());
