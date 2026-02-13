<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/config.php';
header('Content-Type: text/plain; charset=UTF-8');
$base = rtrim((string)config_get('site.base_url', ''), '/');
if ($base === '') {
    $scheme = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = $scheme . '://' . $host . '/public';
}
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo 'Sitemap: ' . $base . "/sitemap.php\n";
