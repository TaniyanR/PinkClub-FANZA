<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$page = safe_int($_GET['page'] ?? 1, 1, 1, 100000);
$limit = 24;
$offset = ($page - 1) * $limit;
[$makers, $hasNext] = paginate_items(fetch_makers($limit + 1, $offset), $limit);

$pageTitle = 'メーカー一覧';
$pageDescription = 'メーカー一覧ページです。';
$canonicalUrl = canonical_url('/makers.php', ['page' => $page > 1 ? (string)$page : null]);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="main-content">
        <section class="block">
            <div class="section-head"><h1 class="section-title">メーカー一覧</h1></div>
            <div class="taxonomy-grid">
                <?php foreach ($makers as $maker) : ?>
                    <a class="taxonomy-card" href="/maker.php?id=<?php echo urlencode((string)$maker['id']); ?>">
                        <div class="taxonomy-card__media">#<?php echo e((string)$maker['id']); ?></div>
                        <div class="taxonomy-card__name"><?php echo e((string)$maker['name']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <nav class="pagination">
            <?php if ($page > 1) : ?><a class="page-btn" href="/makers.php?page=<?php echo e((string)($page - 1)); ?>">前へ</a><?php else : ?><span class="page-btn">前へ</span><?php endif; ?>
            <span class="page-btn is-current"><?php echo e((string)$page); ?></span>
            <?php if ($hasNext) : ?><a class="page-btn" href="/makers.php?page=<?php echo e((string)($page + 1)); ?>">次へ</a><?php else : ?><span class="page-btn">次へ</span><?php endif; ?>
        </nav>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
