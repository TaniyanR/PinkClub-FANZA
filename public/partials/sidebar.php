<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/_helpers.php';

$sortMode = site_setting_get('link.sort_mode', 'registered');
$orderBy = $sortMode === 'kana' ? 'ps.name ASC, ps.id ASC' : 'ps.id DESC';

$partnerLinks = [];
$rssLinks = [];
$fixedPages = [];

try {
    $stmt = db()->query("SELECT ps.id, ps.name, ps.url, COALESCE(ps.show_link, ps.is_enabled, 1) AS show_link FROM partner_sites ps WHERE COALESCE(ps.show_link, ps.is_enabled, 1) = 1 ORDER BY {$orderBy}");
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $seenPartnerUrls = [];
    foreach ($rows as $row) {
        $url = rss_normalize_url((string)($row['url'] ?? ''));
        if ($url === '' || isset($seenPartnerUrls[$url])) {
            continue;
        }
        $seenPartnerUrls[$url] = true;
        $partnerLinks[] = $row;
    }
} catch (Throwable $e) {
    $partnerLinks = [];
}

try {
    $stmt = db()->query('SELECT pr.feed_url, ps.name FROM partner_rss pr INNER JOIN partner_sites ps ON ps.id = pr.partner_site_id WHERE COALESCE(pr.show_rss, pr.is_enabled, 1)=1 ORDER BY pr.id DESC');
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $seenFeeds = [];
    foreach ($rows as $row) {
        $feedUrl = rss_normalize_url((string)($row['feed_url'] ?? ''));
        if ($feedUrl === '' || isset($seenFeeds[$feedUrl])) {
            continue;
        }
        $seenFeeds[$feedUrl] = true;
        $rssLinks[] = $row;
    }
} catch (Throwable $e) {
    $rssLinks = [];
}

try {
    $stmt = db()->query('SELECT slug,title FROM fixed_pages WHERE is_published=1 ORDER BY id ASC');
    $fixedPages = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $fixedPages = [];
}
?>
<aside class="sidebar site-sidebar">
    <section class="sidebar-block">
        <h2 class="sidebar-block__title">検索</h2>
        <form method="get" action="<?= e(public_url('posts.php')) ?>" class="sidebar-search-form">
            <input type="text" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="タイトル/説明を検索" class="sidebar-search-form__input">
            <button type="submit" class="sidebar-search-form__button">検索</button>
        </form>
    </section>

    <section class="sidebar-block">
      <h2 class="sidebar-block__title">固定ページ</h2>
      <?php if ($fixedPages === []): ?>
        <p class="sidebar-empty">固定ページ（未設定）</p>
      <?php else: ?>
      <ul class="sidebar-links">
        <?php foreach ($fixedPages as $page): ?>
          <li><a href="<?= e(public_url('page.php?slug=' . (string)$page['slug'])) ?>"><?= e((string)$page['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>


    <section class="sidebar-block">
      <h2 class="sidebar-block__title">API一覧</h2>
      <ul class="sidebar-links">
        <li><a href="<?= e(public_url('items.php')) ?>">商品一覧</a></li>
        <li><a href="<?= e(public_url('actresses.php')) ?>">女優一覧</a></li>
        <li><a href="<?= e(public_url('genres.php')) ?>">ジャンル一覧</a></li>
        <li><a href="<?= e(public_url('makers.php')) ?>">メーカー一覧</a></li>
        <li><a href="<?= e(public_url('series_list.php')) ?>">シリーズ一覧</a></li>
        <li><a href="<?= e(public_url('authors.php')) ?>">作者一覧</a></li>
      </ul>
    </section>

    <section class="sidebar-block only-pc">

    <section class="sidebar-block">
        <h2 class="sidebar-block__title">相互リンク</h2>
        <?php if ($partnerLinks === []) : ?>
            <p class="sidebar-empty">相互リンク（未設定）</p>
        <?php else : ?>
            <ul class="sidebar-links sidebar-links--scroll">
                <?php foreach ($partnerLinks as $link) : ?>
                    <li><a href="<?= e((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$link['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="sidebar-block only-pc">
      <h2 class="sidebar-block__title">サイトRSS</h2>
      <?php include __DIR__ . '/rss_text_widget.php'; ?>
    </section>

    <section class="sidebar-block only-pc">
    <section class="sidebar-block">
      <h2 class="sidebar-block__title">テキストRSS</h2>
      <?php include __DIR__ . '/rss_text_widget.php'; ?>
    </section>

    <section class="sidebar-block">
      <h2 class="sidebar-block__title">提携RSS</h2>
      <?php if ($rssLinks === []): ?>
        <p class="sidebar-empty">RSS（未設定）</p>
      <?php else: ?>
      <ul class="sidebar-links sidebar-links--scroll">
        <?php foreach ($rssLinks as $rss): ?>
          <li><a href="<?= e((string)$rss['feed_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$rss['name']) ?> RSS</a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <section class="sidebar-block">
      <h2 class="sidebar-block__title">画像RSS</h2>
      <?php include __DIR__ . '/rss_image_widget.php'; ?>
    </section>

    <section class="sidebar-block only-pc">
      <?php $pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home'; render_ad('sidebar_bottom', $pageType, 'pc'); ?>
    <section class="sidebar-block">
      <?php $pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home'; render_ad('sidebar_bottom', $pageType, ad_current_device()); ?>

    <section class="sidebar-block">
      <?php $pageType = function_exists('ad_current_page_type') ? ad_current_page_type() : 'home'; render_ad('sidebar_bottom', $pageType, ad_current_device()); ?>
    </section>
</aside>
