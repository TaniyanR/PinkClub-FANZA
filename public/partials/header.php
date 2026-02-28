<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';
$pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home';

$siteName = trim((string)app_setting_get('site_name', ''));
if ($siteName === '') {
    $siteName = trim((string)app_setting_get('site.title', ''));
}
if ($siteName === '') {
    $siteName = 'PinkClub-FANZA';
}

$headerAdHtml = trim((string)app_setting_get('header_ad_html', ''));
$titleText = (string)($title ?? $pageTitle ?? 'TOP');
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titleText ?: APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="site-header__top">
    <div class="site-header__left">
      <div class="site-title"><?= e($titleText) ?></div>
      <div class="site-disclaimer">当サイトはアフィリエイト広告を利用しています。</div>
    </div>
    <div class="site-header__right">
      <div class="site-name"><?= e($siteName) ?></div>
      <?php if ($headerAdHtml !== '') : ?>
        <div class="site-ad"><?= $headerAdHtml ?></div>
      <?php elseif (should_show_ad('header_left_728x90', $pageType, 'pc')) : ?>
        <div class="site-ad"><?php render_ad('header_left_728x90', $pageType, 'pc'); ?></div>
      <?php endif; ?>
      <div class="only-sp">
        <?php render_ad('sp_header_below', $pageType, 'sp'); ?>
      </div>
    </div>
  </div>

  <nav class="site-nav">
    <a href="<?= e(public_url('')) ?>">TOP</a>
    <a href="<?= e(public_url('items.php')) ?>">商品一覧</a>
    <a href="<?= e(public_url('actresses.php')) ?>">女優一覧</a>
    <a href="<?= e(public_url('genres.php')) ?>">ジャンル一覧</a>
    <a href="<?= e(public_url('makers.php')) ?>">メーカー一覧</a>
    <a href="<?= e(public_url('series.php')) ?>">シリーズ一覧</a>
    <a href="<?= e(public_url('authors.php')) ?>">作者一覧</a>
  </nav>
</header>
<div class="site-layout">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <main class="site-main">
