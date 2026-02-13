<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/partials/_helpers.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    include __DIR__ . '/404.php';
    exit;
}

$st = db()->prepare('SELECT * FROM fixed_pages WHERE slug=:slug AND is_published=1 LIMIT 1');
$st->execute([':slug' => $slug]);
$p = $st->fetch(PDO::FETCH_ASSOC);
if (!is_array($p)) {
    include __DIR__ . '/404.php';
    exit;
}

$pageTitle = (string)((($p['seo_title'] ?? '') !== '') ? $p['seo_title'] : $p['title']);
$pageDescription = (string)($p['seo_description'] ?? '');

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="main-content">
        <?php render_ad('content_top', 'page', 'pc'); ?>
        <section class="block">
            <h1 class="section-title"><?php echo e((string)$p['title']); ?></h1>
            <?php echo (string)$p['body']; ?>
        </section>
        <?php render_ad('content_bottom', 'page', 'pc'); ?>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
