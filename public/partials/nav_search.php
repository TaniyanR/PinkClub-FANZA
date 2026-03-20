<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

$navItems = [
    ['href' => public_url(''), 'label' => 'TOP'],
    ['href' => public_url('items.php'), 'label' => '商品一覧'],
    ['href' => public_url('actresses.php'), 'label' => '女優一覧'],
    ['href' => public_url('genres.php'), 'label' => 'ジャンル一覧'],
    ['href' => public_url('makers.php'), 'label' => 'メーカー一覧'],
    ['href' => public_url('series_list.php'), 'label' => 'シリーズ一覧'],
    ['href' => public_url('authors.php'), 'label' => '作者一覧'],
];
?>
<nav class="site-nav" aria-label="グローバルナビゲーション">
    <?php foreach ($navItems as $index => $item) : ?>
        <?php $isActive = $path === parse_url($item['href'], PHP_URL_PATH); ?>
        <?php if ($index > 0): ?><span class="site-nav__sep" aria-hidden="true"> | </span><?php endif; ?>
        <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
    <?php endforeach; ?>
</nav>
