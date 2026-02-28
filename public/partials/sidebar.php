<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/url.php';
require_once __DIR__ . '/_helpers.php';

$mutualLinks = [];
try {
    $stmt = db()->query("SELECT id, site_name, site_url, link_url, banner_image_url, image_url FROM mutual_links WHERE status='approved' ORDER BY id DESC LIMIT 50");
    $mutualLinks = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $mutualLinks = [];
}

$resolveLinkUrl = static function (array $row): string {
    $id = (int)($row['id'] ?? 0);
    if ($id > 0) {
        return base_path() . '/public/out.php?id=' . $id;
    }

    $raw = trim((string)($row['link_url'] ?? $row['site_url'] ?? ''));
    return $raw;
};

$getImageUrl = static function (array $row): string {
    foreach (['banner_image_url', 'image_url'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
};
?>
<aside class="site-sidebar">
    <section class="sidebar-block">
        <h2 class="sidebar-block__title">検索</h2>
        <form method="get" action="<?= e(public_url('posts.php')) ?>" class="sidebar-search-form">
            <input type="text" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="キーワードを入力" class="sidebar-search-form__input">
            <button type="submit" class="sidebar-search-form__button">検索</button>
        </form>
    </section>

    <section class="sidebar-block">
        <h2 class="sidebar-block__title">画像リンク</h2>
        <?php $imageLinks = array_values(array_filter($mutualLinks, static fn(array $row): bool => $getImageUrl($row) !== '')); ?>
        <?php if ($imageLinks === []) : ?>
            <p class="sidebar-empty">画像リンク（準備中）</p>
        <?php else : ?>
            <ul class="sidebar-image-links">
                <?php foreach ($imageLinks as $link) : ?>
                    <?php $href = $resolveLinkUrl($link); ?>
                    <?php $img = $getImageUrl($link); ?>
                    <li>
                        <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?= e($img) ?>" alt="<?= e((string)($link['site_name'] ?? '画像リンク')) ?>">
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="sidebar-block">
        <h2 class="sidebar-block__title">相互リンク</h2>
        <?php if ($mutualLinks === []) : ?>
            <p class="sidebar-empty">相互リンク（準備中）</p>
        <?php else : ?>
            <ul class="sidebar-links">
                <?php foreach ($mutualLinks as $link) : ?>
                    <li>
                        <a href="<?= e($resolveLinkUrl($link)) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)($link['site_name'] ?? 'リンク')) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</aside>
