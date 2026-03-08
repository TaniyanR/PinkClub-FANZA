<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

$q      = safe_str($_GET['q'] ?? '', 100);
$page   = safe_int($_GET['page'] ?? 1, 1, 1, 100000);
$limit  = 24;
$offset = ($page - 1) * $limit;

if ($q !== '') {
    $stmt = db()->prepare(
        'SELECT * FROM actresses WHERE name LIKE :q OR ruby LIKE :q ORDER BY name ASC LIMIT :l OFFSET :o'
    );
    $like = '%' . $q . '%';
    $stmt->bindValue(':q', $like);
    $stmt->bindValue(':l', $limit + 1, PDO::PARAM_INT);
    $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];
} else {
    $rows = fetch_actresses($limit + 1, $offset, 'name');
}

[$actresses, $hasNext] = paginate_items($rows, $limit);

$pageTitle       = '女優一覧';
$pageDescription = '女優の一覧ページです。';
$canonicalUrl    = canonical_url('/actresses.php', ['q' => $q !== '' ? $q : null, 'page' => $page > 1 ? (string)$page : null]);

include __DIR__ . '/partials/header.php';
?>
        <section class="block">
            <div class="section-head"><h1 class="section-title">女優一覧</h1></div>
            <form class="taxonomy-search" method="get" action="">
                <input class="taxonomy-search__input" type="search" name="q" value="<?php echo e($q); ?>" placeholder="女優名で検索…" maxlength="100">
                <button class="taxonomy-search__btn" type="submit">検索</button>
                <?php if ($q !== ''): ?>
                    <a class="page-btn" href="<?php echo e(build_url('/actresses.php')); ?>">クリア</a>
                <?php endif; ?>
            </form>
            <div class="taxonomy-grid">
                <?php foreach ($actresses as $actress): ?>
                    <a class="taxonomy-card" href="/actress.php?id=<?php echo urlencode((string)$actress['id']); ?>">
                        <?php if (!empty($actress['image_url'])): ?>
                            <div class="taxonomy-card__media"><img src="<?php echo e((string)$actress['image_url']); ?>" alt="<?php echo e((string)$actress['name']); ?>"></div>
                        <?php else: ?>
                            <div class="taxonomy-card__media">♀</div>
                        <?php endif; ?>
                        <div class="taxonomy-card__name"><?php echo e((string)$actress['name']); ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($actresses)): ?>
                    <p>該当する女優が見つかりませんでした。</p>
                <?php endif; ?>
            </div>
        </section>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a class="page-btn" href="<?php echo e(build_url('/actresses.php', ['q' => $q !== '' ? $q : null, 'page' => (string)($page - 1)])); ?>">前へ</a>
            <?php else: ?>
                <span class="page-btn">前へ</span>
            <?php endif; ?>
            <span class="page-btn is-current"><?php echo e((string)$page); ?></span>
            <?php if ($hasNext): ?>
                <a class="page-btn" href="<?php echo e(build_url('/actresses.php', ['q' => $q !== '' ? $q : null, 'page' => (string)($page + 1)])); ?>">次へ</a>
            <?php else: ?>
                <span class="page-btn">次へ</span>
            <?php endif; ?>
        </nav>
<?php include __DIR__ . '/partials/footer.php'; ?>

