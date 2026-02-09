<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function admin_basic_auth_required(): void
{
    $basicUser = (string)config_get('admin.basic_user', '');
    $basicPass = (string)config_get('admin.basic_pass', '');

    if ($basicUser === '' && $basicPass === '') {
        return;
    }

    $sentUser = $_SERVER['PHP_AUTH_USER'] ?? null;
    $sentPass = $_SERVER['PHP_AUTH_PW'] ?? null;

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (($sentUser === null || $sentPass === null) && is_string($authHeader) && stripos($authHeader, 'basic ') === 0) {
        $decoded = base64_decode(substr($authHeader, 6), true);
        if (is_string($decoded) && str_contains($decoded, ':')) {
            [$sentUser, $sentPass] = explode(':', $decoded, 2);
        }
    }

    $userOk = is_string($sentUser) && hash_equals($basicUser, $sentUser);
    $passOk = false;
    if (is_string($sentPass)) {
        $passInfo = password_get_info($basicPass);
        if (($passInfo['algo'] ?? 0) !== 0) {
            $passOk = password_verify($sentPass, $basicPass);
        } else {
            $passOk = hash_equals($basicPass, $sentPass);
        }
    }

    if ($userOk && $passOk) {
        return;
    }

    header('WWW-Authenticate: Basic realm="Admin"');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}
