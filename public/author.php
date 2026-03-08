<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$id = safe_int($_GET['id'] ?? 0, 0, 0, 2147483647);
if ($id < 1) {
    abort_404('404 Not Found', '作者IDが不正です。');
}

$author = fetch_author($id);
if ($author === null) {
    abort_404('404 Not Found', '指定の作者が見つかりませんでした。');
}

$page   = safe_int($_GET['page'] ?? 1, 1, 1, 100000);
$limit  = 12;
$offset = ($page - 1) * $limit;
[$items, $hasNext] = paginate_items(fetch_items_by_author((int)$author['id'], $limit + 1, $offset), $limit);

$pageTitle       = sprintf('%s | 作者', (string)$author['name']);
$pageDescription = sprintf('%s の作品一覧。', (string)$author['name']);
$canonicalUrl    = canonical_url('/author.php', ['id' => (string)$author['id'], 'page' => $page > 1 ? (string)$page : null]);

include __DIR__ . '/partials/header.php';
?>
        <section class="block">
            <h1 class="section-title"><?php echo e((string)$author['name']); ?></h1>
            <?php if (!empty($author['ruby'])): ?><p><?php echo e((string)$author['ruby']); ?></p><?php endif; ?>
        </section>
        <section class="block">
            <h2 class="section-title">関連作品</h2>
            <div class="product-grid product-grid--4">
                <?php foreach ($items as $item): ?>
                    <article class="product-card">
                        <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">
                            <img src="<?php echo e((string)($item['image_small'] ?: $item['image_large'])); ?>" alt="<?php echo e((string)$item['title']); ?>">
                        </a>
                        <div class="product-card__body">
                            <a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e((string)$item['title']); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($items)): ?><p>関連作品はまだありません。</p><?php endif; ?>
            </div>
        </section>
        <nav class="pagination">
            <?php if ($page > 1): ?><a class="page-btn" href="<?php echo e(build_url('/author.php', ['id' => (string)$author['id'], 'page' => (string)($page - 1)])); ?>">前へ</a><?php else: ?><span class="page-btn">前へ</span><?php endif; ?>
            <span class="page-btn is-current"><?php echo e((string)$page); ?></span>
            <?php if ($hasNext): ?><a class="page-btn" href="<?php echo e(build_url('/author.php', ['id' => (string)$author['id'], 'page' => (string)($page + 1)])); ?>">次へ</a><?php else: ?><span class="page-btn">次へ</span><?php endif; ?>
        </nav>
<?php include __DIR__ . '/partials/footer.php'; ?>

