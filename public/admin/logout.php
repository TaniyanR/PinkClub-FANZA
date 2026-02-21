<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        http_response_code(400);
        echo '不正なリクエストです。';
        exit;
    }
} elseif ($method === 'GET') {
    $token = $_GET['_token'] ?? null;
    if ($token !== null && !csrf_verify(is_string($token) ? $token : null)) {
        http_response_code(400);
        echo '不正なリクエストです。';
        exit;
    }
} else {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

admin_logout();
app_redirect(login_url());
