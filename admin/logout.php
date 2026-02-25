<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
admin_logout();
header('Location: /admin/login.php');
