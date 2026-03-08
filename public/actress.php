<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$id = safe_int($_GET['id'] ?? 0, 0, 0, 2147483647);
if ($id < 1) {
    abort_404('404 Not Found', '女優IDが不正です。');
}

$actress = fetch_actress($id);
if ($actress === null) {
    abort_404('404 Not Found', '指定の女優が見つかりませんでした。');
}

$page   = safe_int($_GET['page'] ?? 1, 1, 1, 100000);
$limit  = 12;
$offset = ($page - 1) * $limit;
[$items, $hasNext] = paginate_items(fetch_items_by_actress((int)$actress['id'], $limit + 1, $offset), $limit);

$pageTitle       = sprintf('%s | 女優', (string)$actress['name']);
$pageDescription = sprintf('%s の作品一覧。', (string)$actress['name']);
$canonicalUrl    = canonical_url('/actress.php', ['id' => (string)$actress['id'], 'page' => $page > 1 ? (string)$page : null]);

include __DIR__ . '/partials/header.php';
?>
        <section class="block">
            <h1 class="section-title"><?php echo e((string)$actress['name']); ?></h1>
            <div class="actress-profile">
                <?php if (!empty($actress['image_url'])): ?>
                    <img class="actress-profile__image" src="<?php echo e((string)$actress['image_url']); ?>" alt="<?php echo e((string)$actress['name']); ?>">
                <?php endif; ?>
                <div class="actress-profile__info">
                    <dl>
                        <?php if (!empty($actress['ruby'])): ?><dt>読み</dt><dd><?php echo e((string)$actress['ruby']); ?></dd><?php endif; ?>
                        <?php if (!empty($actress['birthday'])): ?><dt>誕生日</dt><dd><?php echo e((string)$actress['birthday']); ?></dd><?php endif; ?>
                        <?php if (!empty($actress['blood_type'])): ?><dt>血液型</dt><dd><?php echo e((string)$actress['blood_type']); ?></dd><?php endif; ?>
                        <?php if (!empty($actress['prefectures'])): ?><dt>出身</dt><dd><?php echo e((string)$actress['prefectures']); ?></dd><?php endif; ?>
                        <?php if (!empty($actress['height'])): ?><dt>身長</dt><dd><?php echo e((string)$actress['height']); ?>cm</dd><?php endif; ?>
                    </dl>
                </div>
            </div>
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
            <?php if ($page > 1): ?><a class="page-btn" href="<?php echo e(build_url('/actress.php', ['id' => (string)$actress['id'], 'page' => (string)($page - 1)])); ?>">前へ</a><?php else: ?><span class="page-btn">前へ</span><?php endif; ?>
            <span class="page-btn is-current"><?php echo e((string)$page); ?></span>
            <?php if ($hasNext): ?><a class="page-btn" href="<?php echo e(build_url('/actress.php', ['id' => (string)$actress['id'], 'page' => (string)($page + 1)])); ?>">次へ</a><?php else: ?><span class="page-btn">次へ</span><?php endif; ?>
        </nav>
<?php include __DIR__ . '/partials/footer.php'; ?>

