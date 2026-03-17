<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/app_features.php';

$items = [];
try {
    $items = rss_pick_display_items(50, false, 14);
} catch (Throwable $e) {
    $items = [];
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
