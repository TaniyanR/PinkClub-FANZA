<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/_helpers.php';

$showMutualLinks = front_safe_text_setting('links.show_mutual', '1') === '1';
$showRssImages = front_safe_text_setting('links.show_rss_images', '1') === '1';
$linkOrder = front_safe_text_setting('links.order', 'kana');
$orderBy = $linkOrder === 'created' ? 'id DESC' : 'site_name ASC, id ASC';

$mutualLinks = [];
if ($showMutualLinks) {
    try {
        $stmt = db()->query("SELECT id, site_name, site_url, link_url FROM mutual_links WHERE status='approved' AND (is_enabled = 1 OR enabled = 1) ORDER BY {$orderBy} LIMIT 100");
        $mutualLinks = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $mutualLinks = [];
    }
}

$rssItems = [];
if ($showRssImages) {
    try {
        $rssItems = rss_pick_display_items(5, true, 14);
    } catch (Throwable $e) {
        $rssItems = [];
    }
}

$fixedPages = [];
try {
    $stmt = db()->query('SELECT slug,title FROM fixed_pages WHERE is_published=1 ORDER BY id ASC');
    $fixedPages = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $fixedPages = [];
}

$resolveLinkUrl = static function (array $row): string {
    $id = (int)($row['id'] ?? 0);
    if ($id > 0) {
        return public_url('out.php?id=' . $id);
    }

    return trim((string)($row['link_url'] ?? $row['site_url'] ?? '#'));
};
?>
<aside class="sidebar site-sidebar">
    <section class="sidebar-block">
        <h2 class="sidebar-block__title">検索</h2>
        <form method="get" action="<?= e(public_url('posts.php')) ?>" class="sidebar-search-form">
            <input type="text" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="タイトル/説明を検索" class="sidebar-search-form__input">
            <button type="submit" class="sidebar-search-form__button">検索</button>
        </form>
    </section>

    <?php if ($fixedPages !== []): ?>
    <section class="sidebar-block">
        <h2 class="sidebar-block__title">固定ページ</h2>
        <ul class="sidebar-links">
            <?php foreach ($fixedPages as $page): ?>
                <li><a href="<?= e(public_url('page.php?slug=' . (string)$page['slug'])) ?>"><?= e((string)$page['title']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($showMutualLinks): ?>
    <section class="sidebar-block sidebar-block--scroll-links">
        <h2 class="sidebar-block__title">相互リンク</h2>
        <?php if ($mutualLinks === []) : ?>
            <p class="sidebar-empty">相互リンク（未設定）</p>
        <?php else : ?>
            <ul class="sidebar-links">
                <?php foreach ($mutualLinks as $link) : ?>
                    <li><a href="<?= e($resolveLinkUrl($link)) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)($link['site_name'] ?? 'リンク')) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($showRssImages): ?>
    <section class="sidebar-block sidebar-block--rss-images">
        <h2 class="sidebar-block__title">画像RSS</h2>
        <?php if ($rssItems === []): ?>
            <p class="sidebar-empty">RSS（未設定）</p>
        <?php else: ?>
            <ul class="sidebar-image-links sidebar-image-links--rss">
                <?php foreach ($rssItems as $item): ?>
                    <li>
                        <a href="<?= e((string)($item['link'] ?? '')) ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?= e((string)($item['image_url'] ?? '')) ?>" alt="<?= e((string)($item['title'] ?? 'RSS')) ?>">
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if (should_show_ad('sidebar_bottom', ad_current_page_type(), 'pc')): ?>
    <section class="sidebar-block">
        <?php render_ad('sidebar_bottom', ad_current_page_type(), 'pc'); ?>
    </section>
    <?php endif; ?>
</aside>
