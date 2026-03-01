<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home';
$siteName = trim(front_safe_text_setting('site_name', ''));
if ($siteName === '') {
    $siteName = trim(front_safe_text_setting('site.title', ''));
}
if ($siteName === '') {
    $siteName = 'PinkClub FANZA';
}

$siteTagline = trim(front_safe_text_setting('site.tagline', ''));
$siteKeywords = trim(front_safe_text_setting('site.keywords', ''));
$siteLogo = trim(front_safe_text_setting('site.logo_url', ''));
$siteFavicon = trim(front_safe_text_setting('site.favicon_url', ''));

$headerAdHtml = trim((string)app_setting_get('header_ad_html', ''));
$titleText = (string)($title ?? $pageTitle ?? $siteName);
$metaDescription = (string)($pageDescription ?? $siteTagline);
$faviconExt = strtolower(pathinfo(parse_url($siteFavicon, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
$faviconType = $faviconExt === 'ico' ? 'image/x-icon' : 'image/png';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titleText) ?></title>
  <?php if ($metaDescription !== ''): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
  <?php if ($siteKeywords !== ''): ?><meta name="keywords" content="<?= e($siteKeywords) ?>"><?php endif; ?>
  <?php if ($siteFavicon !== ''): ?>
    <link rel="icon" href="<?= e($siteFavicon) ?>" type="<?= e($faviconType) ?>">
    <link rel="shortcut icon" href="<?= e($siteFavicon) ?>" type="<?= e($faviconType) ?>">
    <?php if ($faviconType === 'image/png'): ?>
      <link rel="apple-touch-icon" href="<?= e($siteFavicon) ?>">
    <?php endif; ?>
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="site-header__top">
    <div class="header-left site-header__left">
      <?php if ($siteLogo !== ''): ?>
        <div class="site-logo-wrap"><a href="<?= e(public_url('')) ?>"><img class="site-logo" src="<?= e($siteLogo) ?>" alt="<?= e($siteName) ?>"></a></div>
      <?php else: ?>
        <div class="site-title"><?= e($siteName) ?></div>
      <?php endif; ?>
      <div class="site-disclaimer"><strong>当サイトはアフィリエイト広告を利用しています。</strong></div>
    </div>
    <div class="header-right site-header__right">
      <?php if ($headerAdHtml !== '') : ?>
        <div class="site-ad"><?= $headerAdHtml ?></div>
      <?php elseif (should_show_ad('header_right', $pageType, 'pc')) : ?>
        <div class="site-ad"><?php render_ad('header_right', $pageType, 'pc'); ?></div>
      <?php elseif (should_show_ad('header_left_728x90', $pageType, 'pc')) : ?>
        <div class="site-ad"><?php render_ad('header_left_728x90', $pageType, 'pc'); ?></div>
      <?php endif; ?>
      <div class="only-sp"><?php render_ad('sp_header_below', $pageType, 'sp'); ?></div>
    </div>
  </div>

  <?php require __DIR__ . '/nav_search.php'; ?>
</header>
<div class="layout site-layout">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <main class="content site-main">
