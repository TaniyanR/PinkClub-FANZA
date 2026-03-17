<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/app_features.php';
require_once __DIR__ . '/../../lib/db.php';

$items = [];
try {
    $items = rss_pick_display_items(50, false, 14);
} catch (Throwable $e) {
    $items = [];
}

if ($items === []) {
    try {
        $sources = db()->query("SELECT id,last_fetched_at FROM rss_sources WHERE is_enabled=1 ORDER BY COALESCE(last_fetched_at, '1970-01-01 00:00:00') ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($sources as $source) {
            $lastFetched = strtotime((string)($source['last_fetched_at'] ?? '')) ?: 0;
            if ($lastFetched < time() - 900) {
                rss_fetch_source((int)$source['id'], 2);
                break;
            }
        }
        $items = rss_pick_display_items(50, false, 14);
    } catch (Throwable $e) {
        $items = [];
    }
}
?>
<div class="rss-widget rss-widget--text block">
    <div class="rss-box">
        <?php if ($items !== []) : ?>
            <ul class="rss-list">
                <?php foreach ($items as $item) : ?>
                    <li class="rss-list__item">
                        <a href="<?php echo e((string)($item['link'] ?? '')); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)($item['title'] ?? '')); ?></a>
                        <small><?php echo e((string)($item['source_name'] ?? '')); ?> / <?php echo e((string)($item['published_at'] ?? '')); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="sidebar-empty">テキストRSSの記事がありません。</p>
        <?php endif; ?>
    </div>
</div>
