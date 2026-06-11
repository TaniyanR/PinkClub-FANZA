<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

header('Location: ' . public_url('page.php?slug=contact'), true, 302);
exit;
