<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';

function dedupe_items_for_list(array $items): array
{
    $seen = [];
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $contentId = strtolower(trim((string)($item['content_id'] ?? '')));
        $productId = strtolower(trim((string)($item['product_id'] ?? '')));
        $id = trim((string)($item['id'] ?? ''));
        $key = $contentId !== '' ? 'content_id:' . $contentId : ($productId !== '' ? 'product_id:' . $productId : ($id !== '' ? 'id:' . $id : ''));
        if ($key !== '' && isset($seen[$key])) {
            continue;
        }
        if ($key !== '') {
            $seen[$key] = true;
        }
        $result[] = $item;
    }
    return $result;
}

function collect_unique_items_for_page(callable $fetcher, int $limit, int $offset): array
{
    $rows = [];
    $chunkSize = $limit + 1;
    $cursor = max(0, $offset);
    $maxLoops = 5;

    for ($i = 0; $i < $maxLoops; $i++) {
        $chunk = $fetcher($chunkSize, $cursor);
        if (!is_array($chunk) || $chunk === []) {
            break;
        }

        $rows = dedupe_items_for_list(array_merge($rows, $chunk));
        if (count($rows) > $limit) {
            break;
        }

        $fetched = count($chunk);
        $cursor += $fetched;
        if ($fetched < $chunkSize) {
            break;
        }
    }

    return $rows;
}

$orderParam = safe_str($_GET['order'] ?? 'date_desc', 20);
$orderMap = [
    'date_desc' => 'date_published_desc',
    'date_asc' => 'date_published_asc',
    'price_desc' => 'price_min_desc',
    'price_asc' => 'price_min_asc',
    'random' => 'random',
];
$orderParam = normalize_order($orderParam, array_keys($orderMap), 'date_desc');
$order = $orderMap[$orderParam];

$allowedLimits = [12, 24, 48];
$limit = normalize_int((int)($_GET['limit'] ?? 24), 1, 100);
if (!in_array($limit, $allowedLimits, true)) {
    $limit = 24;
}

$page = normalize_int((int)($_GET['page'] ?? 1), 1, 100000);
$offset = ($page - 1) * $limit;
$q = safe_str($_GET['q'] ?? '', 100);

$rows = $q !== ''
    ? collect_unique_items_for_page(static fn(int $chunkLimit, int $chunkOffset): array => search_items($q, $chunkLimit, $chunkOffset), $limit, $offset)
    : collect_unique_items_for_page(static fn(int $chunkLimit, int $chunkOffset): array => fetch_items($order, $chunkLimit, $chunkOffset), $limit, $offset);
[$items, $hasNext] = paginate_items($rows, $limit);

$pageTitle = $q !== '' ? sprintf('検索結果: %s', $q) : '作品一覧';
$pageDescription = $q !== '' ? sprintf('「%s」の検索結果です。', $q) : 'FANZA作品一覧。検索・並び替え・ページング対応。';
$canonicalUrl = canonical_url('/posts.php', [
    'q' => $q,
    'order' => $orderParam !== 'date_desc' ? $orderParam : null,
    'limit' => $limit !== 24 ? (string)$limit : null,
    'page' => $page > 1 ? (string)$page : null,
]);

include __DIR__ . '/partials/header.php';
?>
        <section class="block">
            <div class="section-head">
                <h1 class="section-title">作品一覧</h1>
                <span class="section-sub"><?php echo $q !== '' ? e('検索: ' . $q) : '実データ表示'; ?></span>
            </div>
            <form class="controls" method="get" action="/posts.php">
                <?php if ($q !== '') : ?>
                    <input type="hidden" name="q" value="<?php echo e($q); ?>">
                <?php endif; ?>
                <div class="controls__group">
                    <label>並び替え
                        <select name="order">
                            <option value="date_desc" <?php echo $orderParam === 'date_desc' ? 'selected' : ''; ?>>新着順</option>
                            <option value="date_asc" <?php echo $orderParam === 'date_asc' ? 'selected' : ''; ?>>古い順</option>
                            <option value="price_desc" <?php echo $orderParam === 'price_desc' ? 'selected' : ''; ?>>価格が高い順</option>
                            <option value="price_asc" <?php echo $orderParam === 'price_asc' ? 'selected' : ''; ?>>価格が安い順</option>
                            <option value="random" <?php echo $orderParam === 'random' ? 'selected' : ''; ?>>ランダム</option>
                        </select>
                    </label>
                    <label>表示件数
                        <select name="limit">
                            <?php foreach ($allowedLimits as $candidate) : ?>
                                <option value="<?php echo e((string)$candidate); ?>" <?php echo $limit === $candidate ? 'selected' : ''; ?>><?php echo e((string)$candidate); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button--primary" type="submit">適用</button>
                </div>
            </form>
        </section>

        <section class="block">
            <?php if ($items === []) : ?>
                <p>データが見つかりませんでした。</p>
            <?php else : ?>
                <div class="product-grid product-grid--4">
                    <?php foreach ($items as $item) : ?>
                        <article class="product-card">
                            <a class="product-card__media" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">
                                <img src="<?php echo e((string)($item['image_small'] ?: $item['image_large'])); ?>" alt="<?php echo e((string)$item['title']); ?>">
                            </a>
                            <div class="product-card__body">
                                <a class="product-card__title" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>"><?php echo e((string)$item['title']); ?></a>
                                <small><?php echo e(format_date($item['date_published'] ?? null)); ?> / <?php echo e(format_price($item['price_min'] ?? null)); ?></small>
                                <div class="product-card__actions">
                                    <a class="button" href="/item.php?cid=<?php echo urlencode((string)$item['content_id']); ?>">詳細</a>
                                    <?php if (!empty($item['affiliate_url'])) : ?>
                                        <a class="button button--primary" href="<?php echo e((string)$item['affiliate_url']); ?>" target="_blank" rel="noopener noreferrer">購入</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <nav class="pagination" aria-label="ページネーション">
            <?php
            $baseQuery = ['q' => $q, 'order' => $orderParam, 'limit' => (string)$limit];
            ?>
            <?php if ($page > 1) : ?>
                <a class="page-btn" href="<?php echo e(build_url('/posts.php', array_merge($baseQuery, ['page' => (string)($page - 1)]))); ?>">前へ</a>
            <?php else : ?>
                <span class="page-btn">前へ</span>
            <?php endif; ?>
            <span class="page-btn is-current"><?php echo e((string)$page); ?></span>
            <?php if ($hasNext) : ?>
                <a class="page-btn" href="<?php echo e(build_url('/posts.php', array_merge($baseQuery, ['page' => (string)($page + 1)]))); ?>">次へ</a>
            <?php else : ?>
                <span class="page-btn">次へ</span>
            <?php endif; ?>
        </nav>
<?php include __DIR__ . '/partials/footer.php'; ?>
