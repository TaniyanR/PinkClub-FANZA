<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 24;
$offset = ($page - 1) * $limit;
[$labels, $hasNext] = paginate_items(fetch_labels($limit + 1, $offset), $limit);

$pageTitle = 'レーベル一覧 | PinkClub-FANZA';
$pageDescription = 'レーベル一覧ページです。';
$canonicalUrl = canonical_url('/labels.php', ['page' => $page > 1 ? $page : null]);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="main-content">
        <section class="block">
            <div class="section-head"><h1 class="section-title">レーベル一覧</h1></div>
            <div class="taxonomy-grid">
                <?php foreach ($labels as $label) : ?>
                    <a class="taxonomy-card" href="/posts.php?q=<?php echo urlencode((string)$label['name']); ?>">
                        <div class="taxonomy-card__media">Label</div>
                        <div class="taxonomy-card__name"><?php echo e((string)$label['name']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <nav class="pagination">
            <?php if ($page > 1) : ?><a class="page-btn" href="/labels.php?page=<?php echo e((string)($page - 1)); ?>">前へ</a><?php else : ?><span class="page-btn">前へ</span><?php endif; ?>
            <span class="page-btn is-current"><?php echo e((string)$page); ?></span>
            <?php if ($hasNext) : ?><a class="page-btn" href="/labels.php?page=<?php echo e((string)($page + 1)); ?>">次へ</a><?php else : ?><span class="page-btn">次へ</span><?php endif; ?>
        </nav>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
