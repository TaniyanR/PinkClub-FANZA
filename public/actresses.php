<?php
declare(strict_types=1);

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$page = safe_int($_GET['page'] ?? 1, 1, 1, 100000);
$limit = 24;
$offset = ($page - 1) * $limit;
[$actresses, $hasNext] = paginate_items(fetch_actresses($limit + 1, $offset), $limit);

$pageTitle = '女優一覧 | PinkClub-FANZA';
$pageDescription = '登録済み女優の一覧ページです。';
$canonicalUrl = canonical_url('/actresses.php', ['page' => $page > 1 ? $page : null]);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/nav_search.php';
?>
<div class="layout">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="main-content">
        <section class="block">
            <div class="section-head"><h1 class="section-title">女優一覧</h1><span class="section-sub">実データ表示</span></div>
            <div class="actress-grid">
                <?php foreach ($actresses as $actress) : ?>
                    <article class="actress-card">
                        <a class="actress-card__media" href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>"><img src="<?php echo e((string)($actress['image_small'] ?: $actress['image_large'])); ?>" alt="<?php echo e((string)$actress['name']); ?>"></a>
                        <a class="actress-card__name" href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>"><?php echo e((string)$actress['name']); ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <nav class="pagination">
            <?php if ($page > 1) : ?><a class="page-btn" href="/actresses.php?page=<?php echo e((string)($page - 1)); ?>">前へ</a><?php else : ?><span class="page-btn">前へ</span><?php endif; ?>
            <span class="page-btn is-current"><?php echo e((string)$page); ?></span>
            <?php if ($hasNext) : ?><a class="page-btn" href="/actresses.php?page=<?php echo e((string)($page + 1)); ?>">次へ</a><?php else : ?><span class="page-btn">次へ</span><?php endif; ?>
        </nav>
    </main>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
