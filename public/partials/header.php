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

$headerAdHtml = trim((string)app_setting_get('header_ad_html', ''));
$titleText = (string)($title ?? $pageTitle ?? $siteName);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titleText) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="site-header__top">
    <div class="header-left site-header__left">
      <div class="site-title"><?= e($siteName) ?></div>
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
