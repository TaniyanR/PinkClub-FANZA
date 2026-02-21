<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$token = $_POST['_token'] ?? null;
if (!csrf_verify(is_string($token) ? $token : null)) {
    http_response_code(400);
    echo '不正なリクエストです。';
    exit;
}

admin_logout();

app_redirect(login_url());
