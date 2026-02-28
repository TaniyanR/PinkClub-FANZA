<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/app_features.php';
require_once __DIR__ . '/partials/_helpers.php';

$pdo = db();
$sources = $pdo->query('SELECT * FROM rss_sources WHERE is_enabled=1')->fetchAll(PDO::FETCH_ASSOC);
foreach ($sources as $source) {
    if (empty($source['last_fetched_at']) || strtotime((string)$source['last_fetched_at']) < time() - 900) {
        rss_fetch_source((int)$source['id'], 2);
        break;
    }
}

$rows = [];
try {
    $rows = rss_pick_display_items(100, false, 14);
} catch (Throwable $e) {
    $rows = [];
}

$pageTitle = 'RSS';
include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
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
