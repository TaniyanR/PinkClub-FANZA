<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/db.php';

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

try {
    $stmt = db()->query('SELECT id,slug,title FROM fixed_pages WHERE is_published = 1 ORDER BY id ASC');
    $fixedPages = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $excludedSlugs = ['about', 'privacy-policy', 'contact'];
    $excludedTitles = ['サイトについて', 'Privacy Policy', 'お問い合わせ'];
    foreach ($fixedPages as $page) {
        $slug = trim((string)($page['slug'] ?? ''));
        $title = trim((string)($page['title'] ?? ''));
        if ($slug === '' || $title === '') {
            continue;
        }
        if (in_array($slug, $excludedSlugs, true) || in_array($title, $excludedTitles, true)) {
            continue;
        }
        $navItems[] = ['href' => public_url('page.php?id=' . (string)($page['id'] ?? 0)), 'label' => $title];
    }
} catch (Throwable $e) {
}
?>
<nav class="site-nav" aria-label="グローバルナビゲーション">
    <?php foreach ($navItems as $index => $item) : ?>
        <?php $isActive = $path === parse_url($item['href'], PHP_URL_PATH); ?>
        <?php if ($index > 0): ?><span class="site-nav__sep" aria-hidden="true"> | </span><?php endif; ?>
        <a class="<?= $isActive ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
    <?php endforeach; ?>
</nav>
