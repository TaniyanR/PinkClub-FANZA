<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$type = (string)($_GET['type'] ?? '');
$file = basename((string)($_GET['file'] ?? ''));

if ($type !== 'favicon' || $file === '') {
    http_response_code(404);
    exit;
}

$path = __DIR__ . '/uploads/site_settings/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$info = @getimagesize($path);
$mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
if (!in_array($mime, ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . ($mime === 'image/vnd.microsoft.icon' ? 'image/x-icon' : $mime));
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
