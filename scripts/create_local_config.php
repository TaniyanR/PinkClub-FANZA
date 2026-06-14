<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/config.local.php';

if (is_file($path)) {
    fwrite(STDOUT, "config.local.php already exists. Skipped.\n");
    exit(0);
}

$contents = <<<PHP
<?php
return [
    'db' => [
        'host' => '',
        'name' => '',
        'dsn' => '',
        'user' => '',
        'password' => '',
    ],
];
PHP;

if (file_put_contents($path, $contents . "\n") === false) {
    fwrite(STDERR, "Failed to create config.local.php.\n");
    exit(1);
}

fwrite(STDOUT, "Created config.local.php.\n");
