<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home';
$safeTextSetting = static function (string $key, string $default = ''): string {
    if (function_exists('front_safe_text_setting')) {
        return front_safe_text_setting($key, $default);
    }

    try {
        if (function_exists('setting')) {
            $value = setting($key, $default);
            return is_string($value) ? $value : $default;
        }
        if (function_exists('app_setting_get')) {
            $value = app_setting_get($key, $default);
            return is_string($value) ? $value : $default;
        }
    } catch (Throwable $e) {
        if (function_exists('app_log_error')) {
            app_log_error('header safe text setting fallback failed: ' . $key, $e);
        }
    }

    return $default;
};

$siteName = trim($safeTextSetting('site_name', ''));
if ($siteName === '') {
    $siteName = trim($safeTextSetting('site.title', ''));
}
if ($siteName === '') {
    $siteName = 'PinkClub FANZA';
}

$tagline = trim($safeTextSetting('site.tagline', ''));
$keywords = trim($safeTextSetting('site.keywords', ''));
$logoPath = trim($safeTextSetting('site.logo_path', ''));
$faviconPath = trim($safeTextSetting('site.favicon_path', ''));

$headerAdHtml = trim($safeTextSetting('header_ad_html', ''));
$scriptNameForTitle = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$titleText = (string)($title ?? $pageTitle ?? $siteName);
if ($scriptNameForTitle === 'actress.php') {
    $actressTitle = trim((string)($name ?? ''));
    if ($actressTitle !== '') {
        $titleText = $actressTitle;
    }
}
$faviconUrl = $faviconPath !== '' ? asset_url($faviconPath) : '';
$canRenderAd = function_exists('render_ad');
$descriptionText = (string)($pageDescription ?? '');
if ($descriptionText === '') {
    $descriptionText = $tagline;
}
$canonicalHref = isset($canonicalUrl) && is_string($canonicalUrl) && $canonicalUrl !== '' ? $canonicalUrl : '';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titleText) ?></title>
  <?php if ($descriptionText !== ''): ?><meta name="description" content="<?= e($descriptionText) ?>"><?php endif; ?>
  <?php if ($canonicalHref !== ''): ?><link rel="canonical" href="<?= e($canonicalHref) ?>"><?php endif; ?>
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= e($keywords) ?>"><?php endif; ?>
  <?php if ($faviconUrl !== ''): ?>
    <link rel="icon" href="<?= e($faviconUrl) ?>" sizes="any" type="image/x-icon">
    <link rel="icon" href="<?= e($faviconUrl) ?>" type="image/png" sizes="48x48">
    <link rel="apple-touch-icon" href="<?= e($faviconUrl) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('css/public-ui.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="site-header__top">
    <div class="header-left site-header__left">
      <?php if ($logoPath !== ''): ?>
        <div class="site-logo-wrap">
          <a href="<?= e(public_url('')) ?>" class="site-title-link"><img src="<?= e(asset_url($logoPath)) ?>" alt="<?= e($siteName) ?>" class="site-logo"></a>
        </div>
      <?php else: ?>
        <div class="site-title"><a href="<?= e(public_url('')) ?>" class="site-title-link"><?= e($siteName) ?></a></div>
      <?php endif; ?>
      <div class="site-disclaimer"><strong>当サイトはアフィリエイト広告を利用しています。</strong></div>
    </div>
    <div class="header-right site-header__right">
      <?php if ($headerAdHtml !== '') : ?>
        <div class="site-ad"><?= $headerAdHtml ?></div>
      <?php elseif ($canRenderAd && (!function_exists('should_show_ad') || should_show_ad('header_left_728x90', $pageType, 'pc'))) : ?>
        <div class="site-ad"><?php render_ad('header_left_728x90', $pageType, 'pc'); ?></div>
      <?php endif; ?>
      <?php if ($canRenderAd): ?>
      <div class="only-sp"><?php render_ad('sp_footer_above', $pageType, 'sp'); ?></div>
      <div class="only-sp"><?php render_ad('sp_header_below', $pageType, 'sp'); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php require __DIR__ . '/nav_search.php'; ?>
</header>
<div class="layout site-layout">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <main class="content site-main site-main--legacy">
    <?php $scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')); ?>
    <?php $autoBreadcrumbSkip = ['item.php', 'genre.php', 'series_detail.php', 'author.php', 'maker.php', 'actress.php']; ?>
    <?php if ($scriptName !== 'index.php' && !in_array($scriptName, $autoBreadcrumbSkip, true)): ?>
      <nav class="pcf-breadcrumb" aria-label="パンくず">
        <span class="pcf-breadcrumb__item"><a href="<?= e(public_url('')) ?>">ホーム</a></span>
        <span class="pcf-breadcrumb__item"><?= e($titleText) ?></span>
      </nav>
    <?php endif; ?>
    <div class="site-main__body">
