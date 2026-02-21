<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/url.php';

header('Content-Type: text/plain; charset=UTF-8');

$base = rtrim(base_url(), '/');
$sitemapUrl = $base . '/sitemap.xml';

echo "User-agent: *\n";
echo "Disallow: /public/admin/\n";
echo "Disallow: /public/admin/login.php\n";
echo "Disallow: /public/forgot_password.php\n";
echo "Disallow: /public/reset_password.php\n";
echo 'Sitemap: ' . $sitemapUrl . "\n";
