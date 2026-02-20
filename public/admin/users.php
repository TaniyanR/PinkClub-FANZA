<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_log_error('users.php is deprecated and redirected to site settings.');
header('Location: ' . admin_url('settings.php?tab=site'));
exit;
