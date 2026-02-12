<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$siteTitle = (string)config_get('site.title', 'PinkClub-FANZA');
$rawPageTitle = isset($pageTitle) && $pageTitle !== '' ? (string)$pageTitle : 'ログイン';
$fullTitle = $rawPageTitle . ' | ' . $siteTitle;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($fullTitle); ?></title>
    <link rel="stylesheet" href="<?php echo e(base_url() . '/assets/css/login.css'); ?>">
</head>
<body>
<div class="login-shell">
    <header class="login-header">
        <a href="<?php echo e(base_url() . '/index.php'); ?>"><?php echo e($siteTitle); ?></a>
    </header>
    <main class="login-main">
