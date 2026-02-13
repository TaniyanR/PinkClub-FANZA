<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';

$adminDir = realpath(__DIR__ . '/../public/admin');
if ($adminDir === false) {
    fwrite(STDERR, "admin directory not found\n");
    exit(1);
}

$files = glob($adminDir . '/*.php') ?: [];
sort($files);

$skip = [
    '_bootstrap.php',
    '_common.php',
    'login.php',
    'logout.php',
    'page_delete.php',
    'page_edit.php',
    'save_settings.php',
    'menu.php',
];

$results = [];

foreach ($files as $path) {
    $name = basename($path);
    if (in_array($name, $skip, true)) {
        continue;
    }

    $code = <<<'RUN'
error_reporting(E_ALL);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/admin/__TARGET__';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'smoke',
    'email' => null,
    'login_mode' => 'config',
    'password_hash' => '',
];
chdir('__ROOT__');
include '__FILE__';
RUN;

    $code = str_replace(['__TARGET__', '__ROOT__', '__FILE__'], [$name, addslashes($adminDir), addslashes($path)], $code);
    $cmd = 'php -d display_errors=0 -r ' . escapeshellarg($code) . ' >/dev/null 2>&1';
    exec($cmd, $output, $status);

    $ok = $status === 0;
    $results[] = [$name, $ok, $status];

    if (!$ok) {
        log_message('[smoke_admin] failed: ' . $name . ' (exit=' . $status . ')');
    }
}

$failed = 0;
foreach ($results as [$name, $ok, $status]) {
    echo sprintf('[%s] %s', $ok ? 'OK' : 'NG', $name);
    if (!$ok) {
        echo ' (exit=' . $status . ')';
        $failed++;
    }
    echo PHP_EOL;
}

echo PHP_EOL . 'Summary: ' . (count($results) - $failed) . '/' . count($results) . ' passed' . PHP_EOL;

exit($failed > 0 ? 1 : 0);
