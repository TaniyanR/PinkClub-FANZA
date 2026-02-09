<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        http_response_code(400);
        echo 'Invalid CSRF token.';
        exit;
    }
}

admin_logout();

header('Location: /admin/login.php');
exit;
