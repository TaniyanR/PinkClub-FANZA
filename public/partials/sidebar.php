<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/repository.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/url.php';
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/../../lib/site_settings.php';

$sidebarGenres = fetch_genres(8, 0);
$sidebarMakers = fetch_makers(8, 0);
$sidebarSeries = fetch_series(8, 0);

$adHtml = function_exists('app_setting_get') ? (string)app_setting_get('sidebar_ad_html', '') : '';

$scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isHome = $scriptName === 'index.php';
$linksEnabled = site_setting_get('links_display_enabled', '1') === '1';
$showLinks = $isHome && $linksEnabled;

$mutualLinks = [];
if ($showLinks) {
    $sortMode = (string)site_setting_get('links_sort_mode', 'manual');
    $orderBy = 'display_order ASC, id DESC';
    if ($sortMode === 'approved_desc') {
        $orderBy = 'approved_at DESC, id DESC';
    } elseif ($sortMode === 'random') {
        $orderBy = 'RAND()';
    } elseif ($sortMode === 'in_desc') {
        $orderBy = '(SELECT COUNT(*) FROM access_events ae WHERE ae.event_type="in" AND ae.link_id=mutual_links.id AND ae.event_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) DESC, id DESC';
    }

    $sql = "SELECT id,site_name,site_url FROM mutual_links WHERE status='approved' AND is_enabled=1 ORDER BY {$orderBy} LIMIT 50";
    $mutualLinks = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$rssLatest = [];
try {
    $stmt = db()->query('SELECT title,url,image_url FROM rss_items WHERE image_url IS NOT NULL AND image_url<>"" ORDER BY published_at DESC, id DESC LIMIT 5');
    $rssLatest = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $rssLatest = [];
}
?>
<aside class="sidebar" style="--links-box-max-height:420px;">
    <div class="sidebar-block">
        <h3>クイックメニュー</h3>
        <ul>
            <li><a href="/posts.php">作品一覧</a></li>
            <li><a href="/rss.php">RSS</a></li>
            <li><a href="/contact.php">お問い合わせ</a></li>
            <li><a href="/links.php">リンク集</a></li>
        </ul>
    </div>

    <?php if ($mutualLinks !== []) : ?>
        <div class="sidebar-block">
            <h3>相互リンク</h3>
            <ul style="max-height:var(--links-box-max-height);overflow:auto;">
                <?php foreach ($mutualLinks as $link) : ?>
                    <li>
                        <a href="<?php echo e(base_url() . '/out.php?id=' . (string)$link['id']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)$link['site_name']); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="sidebar-block"><h3>ジャンル</h3><ul><?php foreach ($sidebarGenres as $genre) : ?><li><a href="/genre.php?id=<?php echo urlencode((string)$genre['id']); ?>"><?php echo e((string)$genre['name']); ?></a></li><?php endforeach; ?></ul></div>
    <div class="sidebar-block"><h3>メーカー</h3><ul><?php foreach ($sidebarMakers as $maker) : ?><li><a href="/maker.php?id=<?php echo urlencode((string)$maker['id']); ?>"><?php echo e((string)$maker['name']); ?></a></li><?php endforeach; ?></ul></div>
    <div class="sidebar-block"><h3>シリーズ</h3><ul><?php foreach ($sidebarSeries as $series) : ?><li><a href="/series_one.php?id=<?php echo urlencode((string)$series['id']); ?>"><?php echo e((string)$series['name']); ?></a></li><?php endforeach; ?></ul></div>

    <?php if ($rssLatest !== []) : ?>
        <div class="sidebar-block"><h3>画像RSS</h3><ul><?php foreach ($rssLatest as $rss) : ?><li><img src="<?php echo e((string)$rss['image_url']); ?>" alt="" style="max-width:100%;height:auto"><a href="<?php echo e((string)$rss['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)$rss['title']); ?></a></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php
    $canUseNewAd = function_exists('should_show_ad') && function_exists('render_ad') && function_exists('ad_current_page_type');
    if ($canUseNewAd && should_show_ad('sidebar_bottom', ad_current_page_type(), 'pc')) : ?>
        <div class="sidebar-block"><h3>広告枠</h3><?php render_ad('sidebar_bottom', ad_current_page_type(), 'pc'); ?></div>
    <?php elseif ($adHtml !== '') : ?>
        <div class="sidebar-block"><h3>広告枠</h3><?php echo $adHtml; ?></div>
    <?php endif; ?>
</aside>
