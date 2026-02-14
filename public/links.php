<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/partials/_helpers.php';

$rows = db()->query("SELECT id, site_name, site_url FROM mutual_links WHERE status='approved' AND is_enabled=1 ORDER BY display_order ASC, id ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'リンク集';
include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
echo '<div class="layout">';
include __DIR__ . '/partials/sidebar.php';
?>
<main class="main-content"><section class="block"><h1 class="section-title">リンク集</h1>
<ul>
<?php foreach ($rows as $r) : ?>
<li><a href="<?php echo e(base_url() . '/out.php?id=' . (string)$r['id']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)$r['site_name']); ?></a></li>
<?php endforeach; ?>
<?php if ($rows === []) : ?><li>まだリンクがありません。</li><?php endif; ?>
</ul></section></main></div>
<?php include __DIR__ . '/partials/footer.php';
