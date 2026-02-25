<?php declare(strict_types=1); ?>
<!doctype html><html lang="ja"><head><meta charset="UTF-8"><title><?= e($title ?? APP_NAME) ?></title><link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>"></head><body>
<header><h1><a href="<?= e(public_url('index.php')) ?>">PinkClub FANZA</a></h1>
<nav>
<a href="<?= e(public_url('items.php')) ?>">商品</a>
<a href="<?= e(public_url('actresses.php')) ?>">女優</a>
<a href="<?= e(public_url('genres.php')) ?>">ジャンル</a>
<a href="<?= e(public_url('makers.php')) ?>">メーカー</a>
<a href="<?= e(public_url('series_list.php')) ?>">シリーズ</a>
<a href="<?= e(public_url('authors.php')) ?>">作者</a>
</nav></header><main>
