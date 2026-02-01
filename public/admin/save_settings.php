<?php
$config = require __DIR__ . '/../../config.php';

$config['dmm_api']['api_id'] = trim($_POST['api_id'] ?? '');
$config['dmm_api']['affiliate_id'] = trim($_POST['affiliate_id'] ?? '');
$config['dmm_api']['site'] = trim($_POST['site'] ?? 'FANZA');
$config['dmm_api']['service'] = trim($_POST['service'] ?? 'digital');
$config['dmm_api']['floor'] = trim($_POST['floor'] ?? 'videoa');

$export = '<?php' . PHP_EOL
    . 'declare(strict_types=1);' . PHP_EOL . PHP_EOL
    . 'return ' . var_export($config, true) . ';' . PHP_EOL;
file_put_contents(__DIR__ . '/../../config.php', $export);

header('Location: /admin/settings.php?saved=1');
exit;
