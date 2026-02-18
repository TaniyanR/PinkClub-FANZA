<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/url.php';

header('Location: ' . login_path(), true, 302);
exit;
