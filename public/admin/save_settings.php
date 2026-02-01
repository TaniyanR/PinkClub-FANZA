<?php
$config = require __DIR__ . '/../../config.php';

$config['api']['api_id'] = trim($_POST['api_id'] ?? '');
$config['api']['affiliate_id'] = trim($_POST['affiliate_id'] ?? '');
$config['api']['site'] = trim($_POST['site'] ?? 'FANZA');
$config['api']['service'] = trim($_POST['service'] ?? 'digital');
$config['api']['floor'] = trim($_POST['floor'] ?? 'videoa');

$export = '<?php' . PHP_EOL . 'return ' . var_export($config, true) . ';' . PHP_EOL;
file_put_contents(__DIR__ . '/../../config.php', $export);

header('Location: /admin/settings.php?saved=1');
exit;
