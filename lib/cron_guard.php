<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/site_settings.php';

function cron_secret_token(): string
{
    $token = site_setting_get('cron.web_token', '');
    if (strlen($token) < 40) {
        $token = bin2hex(random_bytes(32));
        site_setting_set('cron.web_token', $token);
    }
    return $token;
}

function cron_regenerate_secret_token(): string
{
    $token = bin2hex(random_bytes(32));
    site_setting_set('cron.web_token', $token);
    return $token;
}

function cron_mask_token(string $token): string
{
    return strlen($token) <= 12 ? '********' : substr($token, 0, 6) . '...' . substr($token, -4);
}

function cron_require_authorized_web(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $expected = cron_secret_token();
    $given = (string)($_GET['token'] ?? '');
    if ($given === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        error_log('[cron] forbidden web execution token=' . cron_mask_token($given));
        echo "403 Forbidden\n";
        exit;
    }
}

function cron_with_file_lock(string $name, callable $callback): int
{
    $lockDir = __DIR__ . '/../logs';
    if (!is_dir($lockDir)) { @mkdir($lockDir, 0755, true); }
    $path = $lockDir . '/cron_' . preg_replace('/[^a-z0-9_-]/i', '_', $name) . '.lock';
    $fp = fopen($path, 'c');
    if ($fp === false) {
        error_log('[cron] lock open failed name=' . $name);
        return 1;
    }
    try {
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            error_log('[cron] already running name=' . $name);
            if (PHP_SAPI !== 'cli') { echo "already running\n"; }
            return 0;
        }
        ftruncate($fp, 0);
        fwrite($fp, (string)getmypid() . ' ' . date('c'));
        return (int)$callback();
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
