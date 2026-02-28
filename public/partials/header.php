<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';
$pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? $pageTitle ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>">
</head>
<body>
<header>
  <h1><a href="<?= e(public_url('index.php')) ?>">PinkClub FANZA</a></h1>
  <nav>
    <a href="<?= e(public_url('items.php')) ?>">商品</a>
    <a href="<?= e(public_url('actresses.php')) ?>">女優</a>
    <a href="<?= e(public_url('genres.php')) ?>">ジャンル</a>
    <a href="<?= e(public_url('makers.php')) ?>">メーカー</a>
    <a href="<?= e(public_url('series_list.php')) ?>">シリーズ</a>
    <a href="<?= e(public_url('authors.php')) ?>">作者</a>
  </nav>
  <div class="only-pc">
    <?php render_ad('header_left_728x90', $pageType, 'pc'); ?>
  </div>
  <div class="only-sp">
    <?php render_ad('sp_header_below', $pageType, 'sp'); ?>
    <?php include __DIR__ . '/rss_text_widget.php'; ?>
  </div>
</header>
<main>
