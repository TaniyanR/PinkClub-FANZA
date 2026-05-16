<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/app_features.php';
require_once __DIR__ . '/partials/_helpers.php';

rss_widget_bootstrap();
rss_refresh_stale_sources(1, 900, 2);

$rows = [];
try {
    $rows = rss_pick_display_items(100, false, 14);
} catch (Throwable $e) {
    $rows = [];
}

if (!isset($GLOBALS['pcf_rss_widget_used_keys']) || !is_array($GLOBALS['pcf_rss_widget_used_keys'])) {
    $GLOBALS['pcf_rss_widget_used_keys'] = [];
}
foreach ($rows as $rssRow) {
    if (!is_array($rssRow)) {
        continue;
    }
    $key = rss_normalize_display_key($rssRow);
    if ($key !== '') {
        $GLOBALS['pcf_rss_widget_used_keys'][$key] = true;
    }
}
$rssUsedKeys = [];
$filteredRows = [];
foreach ($rows as $rssRow) {
    if (!is_array($rssRow)) {
        continue;
    }
    $key = rss_normalize_display_key($rssRow);
    if ($key === '') {
        $key = mb_strtolower(trim((string)($rssRow['title'] ?? '')));
    }
    if ($key !== '' && isset($rssUsedKeys[$key])) {
        continue;
    }
    if ($key !== '') {
        $rssUsedKeys[$key] = true;
    }
    $filteredRows[] = $rssRow;
}
$rows = $filteredRows;

$pageTitle = 'RSS';
include __DIR__ . '/partials/header.php';
?>
        <section class="block">
            <h1 class="section-title">RSS一覧</h1>
            <?php foreach ($rows as $row) : ?>
                <article class="rss-list__item">
                    <h3><a href="<?php echo e((string)($row['link'] ?? '')); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)($row['title'] ?? '')); ?></a></h3>
                    <p><?php echo e((string)($row['published_at'] ?? '')); ?> / <?php echo e((string)($row['source_name'] ?? '')); ?></p>
                </article>
            <?php endforeach; ?>
        </section>
<?php include __DIR__ . '/partials/footer.php'; ?>
