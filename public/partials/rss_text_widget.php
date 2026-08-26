<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/../../lib/rss_display_balance.php';
require_once __DIR__ . '/../../lib/db.php';

rss_widget_bootstrap(false);

$items = [];
try {
    $items = rss_pick_display_items(250, false, 14);
    if (is_array($items) && count($items) > 1) {
        $items = rss_balance_items_by_partner_site($items);
    }
} catch (Throwable $e) {
    error_log('[rss] text widget balancing skipped: ' . $e->getMessage());
    $items = [];
}

$rssUsedKeys = [];
if (isset($GLOBALS['pcf_rss_widget_used_keys']) && is_array($GLOBALS['pcf_rss_widget_used_keys'])) {
    $rssUsedKeys = $GLOBALS['pcf_rss_widget_used_keys'];
}
$maxItems = 50;
if (isset($GLOBALS['pcf_rss_widget_max_items'])) {
    $maxItems = min(50, max(0, (int)$GLOBALS['pcf_rss_widget_max_items']));
}

$filteredItems = [];
$sourceCounts = [];
$seenTitles = [];
$maxItemsSourceLimit = 5;
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $key = rss_normalize_display_key($item);
    if ($key === '') {
        $key = mb_strtolower(trim((string)($item['title'] ?? '')));
    }
    if ($key !== '' && isset($rssUsedKeys[$key])) {
        continue;
    }

    $titleKey = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string)($item['title'] ?? ''))) ?? '');
    if ($titleKey !== '' && isset($seenTitles[$titleKey])) {
        continue;
    }

    $sourceKey = rss_partner_display_source_key($item);
    if ($maxItemsSourceLimit > 0 && $sourceKey !== '' && ($sourceCounts[$sourceKey] ?? 0) >= $maxItemsSourceLimit) {
        continue;
    }

    if ($key !== '') {
        $rssUsedKeys[$key] = true;
    }
    if ($titleKey !== '') {
        $seenTitles[$titleKey] = true;
    }
    if ($sourceKey !== '') {
        $sourceCounts[$sourceKey] = ($sourceCounts[$sourceKey] ?? 0) + 1;
    }

    $filteredItems[] = $item;
    if ($maxItems > 0 && count($filteredItems) >= $maxItems) {
        break;
    }
}

$items = $filteredItems;
$GLOBALS['pcf_rss_widget_used_keys'] = $rssUsedKeys;
?>
<div class="rss-widget rss-widget--text block">
    <div class="rss-box">
        <?php if ($items !== []) : ?>
            <ul class="rss-list">
                <?php foreach ($items as $item) : ?>
                    <li class="rss-list__item">
                        <a href="<?php echo e((string)($item['link'] ?? '')); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)($item['title'] ?? '')); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="sidebar-empty">テキストRSSの記事がありません。</p>
        <?php endif; ?>
    </div>
</div>
