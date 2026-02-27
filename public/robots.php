<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/url.php';

header('Content-Type: text/plain; charset=UTF-8');

$base = rtrim(base_url(), '/');
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /public/forgot_password.php\n";
echo "Disallow: /public/reset_password.php\n";
