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
        'SELECT * FROM makers WHERE name LIKE :q OR ruby LIKE :q ORDER BY name ASC LIMIT :l OFFSET :o'
    );
    $like = '%' . $q . '%';
    $stmt->bindValue(':q', $like);
    $stmt->bindValue(':l', $limit + 1, PDO::PARAM_INT);
    $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];
} else {
    $rows = fetch_makers($limit + 1, $offset, 'name');
}

[$makers, $hasNext] = paginate_items($rows, $limit);

$pageTitle       = 'メーカー一覧';
$pageDescription = 'メーカーの一覧ページです。';
$canonicalUrl    = canonical_url('/makers.php', ['q' => $q !== '' ? $q : null, 'page' => $page > 1 ? (string)$page : null]);

include __DIR__ . '/partials/header.php';
?>
        <section class="block">
            <div class="section-head"><h1 class="section-title">メーカー一覧</h1></div>
            <form class="taxonomy-search" method="get" action="">
                <input class="taxonomy-search__input" type="search" name="q" value="<?php echo e($q); ?>" placeholder="メーカー名で検索…" maxlength="100">
                <button class="taxonomy-search__btn" type="submit">検索</button>
                <?php if ($q !== ''): ?>
                    <a class="page-btn" href="/makers.php">クリア</a>
                <?php endif; ?>
            </form>
            <div class="taxonomy-grid">
                <?php foreach ($makers as $maker): ?>
                    <a class="taxonomy-card" href="/maker.php?id=<?php echo urlencode((string)$maker['id']); ?>">
                        <div class="taxonomy-card__media">🏢</div>
                        <div class="taxonomy-card__name"><?php echo e((string)$maker['name']); ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($makers)): ?>
                    <p>該当するメーカーが見つかりませんでした。</p>
                <?php endif; ?>
            </div>
        </section>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a class="page-btn" href="<?php echo e(build_url('/makers.php', ['q' => $q !== '' ? $q : null, 'page' => (string)($page - 1)])); ?>">前へ</a>
            <?php else: ?>
                <span class="page-btn">前へ</span>
            <?php endif; ?>
            <span class="page-btn is-current"><?php echo e((string)$page); ?></span>
            <?php if ($hasNext): ?>
                <a class="page-btn" href="<?php echo e(build_url('/makers.php', ['q' => $q !== '' ? $q : null, 'page' => (string)($page + 1)])); ?>">次へ</a>
            <?php else: ?>
                <span class="page-btn">次へ</span>
            <?php endif; ?>
        </nav>
<?php include __DIR__ . '/partials/footer.php'; ?>

