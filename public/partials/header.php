<?php declare(strict_types=1); ?>
<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title><?= e($title ?? APP_NAME) ?></title><link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>"></head><body>
<header><h1><a href="<?= e(app_url('public/index.php')) ?>">PinkClub FANZA</a></h1>
<nav>
<a href="<?= e(app_url('public/items.php')) ?>">商品</a>
<a href="<?= e(app_url('public/actresses.php')) ?>">女優</a>
<a href="<?= e(app_url('public/genres.php')) ?>">ジャンル</a>
<a href="<?= e(app_url('public/makers.php')) ?>">メーカー</a>
<a href="<?= e(app_url('public/series_list.php')) ?>">シリーズ</a>
<a href="<?= e(app_url('public/authors.php')) ?>">作者</a>
</nav></header><main>
