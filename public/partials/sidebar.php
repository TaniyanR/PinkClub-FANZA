<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/repository.php';
require_once __DIR__ . '/../../lib/admin_auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/url.php';
require_once __DIR__ . '/../../lib/app_features.php';

$sidebarGenres = fetch_genres(8, 0);
$sidebarMakers = fetch_makers(8, 0);
$sidebarSeries = fetch_series(8, 0);
$adHtml = (string)app_setting_get('sidebar_ad_html', '');
$rssLatest = [];
try {
    $stmt = db()->query('SELECT title,url FROM rss_items ORDER BY published_at DESC, id DESC LIMIT 5');
    $rssLatest = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $rssLatest = [];
}
?>
<aside class="sidebar">
    <div class="sidebar-block"><h3>クイックメニュー</h3><ul><li><a href="/posts.php">作品一覧</a></li><li><a href="/rss.php">RSS</a></li><li><a href="/contact.php">お問い合わせ</a></li><li><a href="/links.php">リンク集</a></li></ul></div>
    <div class="sidebar-block"><h3>ジャンル</h3><ul><?php foreach ($sidebarGenres as $genre) : ?><li><a href="/genre.php?id=<?php echo urlencode((string)$genre['id']); ?>"><?php echo e((string)$genre['name']); ?></a></li><?php endforeach; ?></ul></div>
    <div class="sidebar-block"><h3>メーカー</h3><ul><?php foreach ($sidebarMakers as $maker) : ?><li><a href="/maker.php?id=<?php echo urlencode((string)$maker['id']); ?>"><?php echo e((string)$maker['name']); ?></a></li><?php endforeach; ?></ul></div>
    <div class="sidebar-block"><h3>シリーズ</h3><ul><?php foreach ($sidebarSeries as $series) : ?><li><a href="/series_one.php?id=<?php echo urlencode((string)$series['id']); ?>"><?php echo e((string)$series['name']); ?></a></li><?php endforeach; ?></ul></div>
    <div class="sidebar-block"><h3>最新RSS</h3><ul><?php foreach ($rssLatest as $rss) : ?><li><a href="<?php echo e((string)$rss['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)$rss['title']); ?></a></li><?php endforeach; ?></ul></div>
    <div class="sidebar-block"><h3>広告枠</h3><?php if ($adHtml !== '') : ?><?php echo $adHtml; ?><?php else : ?><div class="ad-box ad-box--sidebar" aria-label="広告枠">300x250</div><?php endif; ?></div>
</aside>
