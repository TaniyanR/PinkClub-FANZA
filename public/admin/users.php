<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_log_error('users.php is deprecated and redirected to API settings.');
header('Location: ' . admin_url('api_settings.php'));
exit;
