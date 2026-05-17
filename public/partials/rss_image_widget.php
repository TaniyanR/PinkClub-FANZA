<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/../../lib/db.php';

rss_widget_bootstrap();
rss_refresh_stale_sources(1, 900, 2);

$items = [];
try {
    $items = rss_pick_display_items(5, true, 14);
} catch (Throwable $e) {
    $items = [];
}

$rssUsedKeys = [];
if (isset($GLOBALS['pcf_rss_widget_used_keys']) && is_array($GLOBALS['pcf_rss_widget_used_keys'])) {
    $rssUsedKeys = $GLOBALS['pcf_rss_widget_used_keys'];
}
if ($items !== []) {
    $filteredItems = [];
    foreach ($items as $item) {
        $key = rss_normalize_display_key(is_array($item) ? $item : []);
        if ($key === '') {
            $key = mb_strtolower(trim((string)($item['title'] ?? '')));
        }
        if ($key !== '' && isset($rssUsedKeys[$key])) {
            continue;
        }
        if ($key !== '') {
            $rssUsedKeys[$key] = true;
        }
        $filteredItems[] = $item;
    }
    $items = $filteredItems;
}
$GLOBALS['pcf_rss_widget_used_keys'] = $rssUsedKeys;
?>
<div class="rss-widget rss-widget--image">
    <?php if ($items !== []) : ?>
    <ul class="rss-image-list">
        <?php foreach ($items as $item) : ?>
            <li class="rss-image-list__item">
                <?php if ((string)($item['image_url'] ?? '') !== '') : ?>
                    <img src="<?php echo e((string)$item['image_url']); ?>" alt="" loading="lazy">
                <?php endif; ?>
                <a href="<?php echo e((string)($item['link'] ?? '')); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)($item['title'] ?? '')); ?></a>
                <small><?php echo e((string)($item['source_name'] ?? '')); ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php else : ?>
        <p class="sidebar-empty">画像RSSの記事がありません。</p>
    <?php endif; ?>
</div>