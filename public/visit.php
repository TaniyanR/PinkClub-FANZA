<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST' && $method !== 'GET') {
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    $_POST['path'] = $_GET['path'] ?? '';
    $_POST['referrer'] = $_GET['referrer'] ?? '';
    $_POST['ref'] = $_GET['ref'] ?? '';
}

try {
    analytics_track_beacon();
} catch (Throwable $e) {
    error_log('visit beacon failed: ' . $e->getMessage());
}

http_response_code(204);
