<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/app_features.php';

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

$items = [];
try {
    $items = rss_pick_display_items(5, true, 14);
} catch (Throwable $e) {
    $items = [];
}
?>
<div class="rss-widget rss-widget--image">
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
</div>
