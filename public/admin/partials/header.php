<?php
declare(strict_types=1);

require_once __DIR__ . '/../../partials/_helpers.php';
require_once __DIR__ . '/../../../lib/admin_auth.php';
require_once __DIR__ . '/../../../lib/csrf.php';
require_once __DIR__ . '/../../../lib/url.php';

$siteTitle = (string)config_get('site.title', 'PinkClub-FANZA');
$adminTitle = '管理画面';
$rawPageTitle = isset($pageTitle) && $pageTitle !== '' ? (string)$pageTitle : $adminTitle;
$fullTitle = $rawPageTitle . ' | ' . $siteTitle;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo e($fullTitle); ?></title>
    <link rel="stylesheet" href="/assets/css/common.css">
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <div class="site-header__brand">
            <a class="site-header__title" href="<?php echo e(admin_url('index.php')); ?>"><?php echo e($siteTitle); ?> 管理</a>
        </div>
    </div>
</header>
<div class="site-body">
