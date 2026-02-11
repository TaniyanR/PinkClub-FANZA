<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$siteTitle = (string)config_get('site.title', 'PinkClub-FANZA');
$defaultDescription = (string)config_get('site.description', 'FANZA作品を実データで紹介するPinkClub-FANZA。');

$pageTitle = isset($pageTitle) && $pageTitle !== '' ? (string)$pageTitle : $siteTitle;
$pageDescription = isset($pageDescription) && $pageDescription !== '' ? (string)$pageDescription : $defaultDescription;
$canonicalUrl = isset($canonicalUrl) && $canonicalUrl !== '' ? (string)$canonicalUrl : canonical_url();
$ogImage = isset($ogImage) && $ogImage !== '' ? (string)$ogImage : null;
$ogType = isset($ogType) && $ogType !== '' ? (string)$ogType : 'website';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <meta name="description" content="<?php echo e($pageDescription); ?>">
    <link rel="canonical" href="<?php echo e($canonicalUrl); ?>">
    <meta property="og:type" content="<?php echo e($ogType); ?>">
    <meta property="og:site_name" content="<?php echo e($siteTitle); ?>">
    <meta property="og:title" content="<?php echo e($pageTitle); ?>">
    <meta property="og:description" content="<?php echo e($pageDescription); ?>">
    <meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
    <?php if ($ogImage !== null) : ?>
        <meta property="og:image" content="<?php echo e($ogImage); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/common.css">
    <?php if (isset($pageStyles) && is_array($pageStyles)) : ?>
        <?php foreach ($pageStyles as $stylePath) : ?>
            <link rel="stylesheet" href="<?php echo e((string)$stylePath); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <div class="site-header__brand">
            <a class="site-header__title" href="/">
                <?php echo e($siteTitle); ?>
            </a>
            <div class="site-header__note"><strong>当サイトはアフィリエイト広告を使用しています。</strong></div>
        </div>
        <div class="site-header__ad" aria-label="広告枠">
            <div class="ad-box ad-box--header">728x90</div>
        </div>
    </div>
</header>
<div class="site-body">
