<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/url.php';

$location = login_url();
$returnTo = $_GET['return_to'] ?? '';
if (is_string($returnTo) && $returnTo !== '') {
    $location .= '?return_to=' . rawurlencode($returnTo);
}

header('Location: ' . $location, true, 302);
exit;
