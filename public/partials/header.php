<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/../../lib/site_settings.php';

$siteTitle = front_safe_text_setting('site.name', (string)config_get('site.title', 'PinkClub-FANZA'));
if ($siteTitle === '' || $siteTitle === 'PinkClub-FANZA') {
    $siteTitle = 'サイトタイトル未設定';
}
$siteTagline = front_safe_text_setting('site.tagline', '');
$defaultDescription = $siteTagline !== '' ? $siteTagline : (string)config_get('site.description', '作品紹介サイトです。');

$rawPageTitle = isset($pageTitle) && $pageTitle !== '' ? (string)$pageTitle : null;
$pageDescription = isset($pageDescription) && $pageDescription !== '' ? (string)$pageDescription : $defaultDescription;
$canonicalBase = (string)app_setting_get('canonical_base', '');
$canonicalUrl = isset($canonicalUrl) && $canonicalUrl !== '' ? (string)$canonicalUrl : canonical_url();
if ($canonicalBase !== '') {
    $canonicalUrl = rtrim($canonicalBase, '/') . current_path();
}
$ogImage = isset($ogImage) && $ogImage !== '' ? (string)$ogImage : front_safe_text_setting('design.ogp_image_url', '');
if ($ogImage !== '' && str_starts_with($ogImage, '/')) {
    $ogImage = front_asset_url($ogImage);
}
$ogType = isset($ogType) && $ogType !== '' ? (string)$ogType : 'website';
$fullTitle = $rawPageTitle !== null ? ($rawPageTitle . ' | ' . $siteTitle) : $siteTitle;
$ga4Id = (string)app_setting_get('ga4_measurement_id', '');
$scMeta = (string)app_setting_get('search_console_verification', '');
$themeColor = (string)app_setting_get('theme_color', '');
$headCode = (string)app_setting_get('head_injection_code', '');
$siteRootPath = front_asset_url('/');
try {
    track_page_view($itemCid ?? null);
} catch (Throwable $e) {
    app_log_error('track_page_view failed', $e);
}
$adPageType = ad_current_page_type();
$adDevice = ad_current_device();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($fullTitle); ?></title>
    <meta name="description" content="<?php echo e($pageDescription); ?>">
    <link rel="canonical" href="<?php echo e($canonicalUrl); ?>">
    <?php if ($scMeta !== '') : ?><meta name="google-site-verification" content="<?php echo e($scMeta); ?>"><?php endif; ?>
    <meta property="og:type" content="<?php echo e($ogType); ?>">
    <meta property="og:site_name" content="<?php echo e($siteTitle); ?>">
    <meta property="og:title" content="<?php echo e($fullTitle); ?>">
    <meta property="og:description" content="<?php echo e($pageDescription); ?>">
    <meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
    <?php if ($ogImage !== '') : ?><meta property="og:image" content="<?php echo e($ogImage); ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?php echo e(front_asset_url('/assets/css/site.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(front_asset_url('/assets/css/common.css')); ?>">
    <?php if ($themeColor !== '') : ?><style>:root{--theme-accent:<?php echo e($themeColor); ?>;}</style><?php endif; ?>
    <?php if ($headCode !== '') : ?><?php echo $headCode; ?><?php endif; ?>
    <?php if ($ga4Id !== '') : ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e($ga4Id); ?>"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo e($ga4Id); ?>');</script>
    <?php endif; ?>
    <?php if (isset($pageStyles) && is_array($pageStyles)) : foreach ($pageStyles as $stylePath) :
        $styleHref = (string)$stylePath;
        if ($styleHref !== '' && str_starts_with($styleHref, '/')) {
            $styleHref = front_asset_url($styleHref);
        }
    ?>
        <link rel="stylesheet" href="<?php echo e($styleHref); ?>">
    <?php endforeach; endif; ?>
</head>
<body>
<header class="site-header">
    <div class="site-header__inner">
        <div class="site-header__brand">
            <?php $logoUrl = front_safe_text_setting('design.logo_url', '');
            if ($logoUrl !== '' && str_starts_with($logoUrl, '/')) {
                $logoUrl = front_asset_url($logoUrl);
            } ?>
            <?php if ($logoUrl !== '') : ?><img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($siteTitle); ?>" style="height:36px;width:auto;display:block;margin-bottom:4px;"><?php endif; ?>
            <a class="site-header__title" href="<?php echo e($siteRootPath); ?>"><?php echo e($siteTitle); ?></a>
            <?php if ($siteTagline !== '') : ?><div class="site-header__tagline"><?php echo e($siteTagline); ?></div><?php endif; ?>
            <div class="site-header__note"><strong>当サイトはプロモーションを含みます。</strong></div>
        </div>
        <div>
            <?php if (user_current_email() !== null) : ?>
                ログイン中: <?php echo e((string)user_current_email()); ?> <a href="<?php echo e(front_asset_url('/user_logout.php')); ?>">ログアウト</a>
            <?php else : ?>
                <a href="<?php echo e(front_asset_url('/user_login.php')); ?>">会員ログイン</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php render_ad('header_left_728x90', $adPageType, 'pc'); ?>
<?php render_ad('sp_header_below', $adPageType, 'sp'); ?>
<div class="rss-sp-only">
    <?php include __DIR__ . '/rss_text_widget.php'; ?>
</div>
<div class="site-body">
