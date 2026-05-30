<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';
require_once __DIR__ . '/../lib/repository.php';

function search_item_has_product_source(array $item): bool
{
    if (array_key_exists('item_source', $item)) {
        return (string)($item['item_source'] ?? '') === 'fanza_product';
    }

    foreach (['affiliate_url', 'service_code', 'floor_code', 'sample_movie_url_476', 'sample_movie_url_560', 'sample_movie_url_644', 'sample_movie_url_720'] as $key) {
        if (trim((string)($item[$key] ?? '')) !== '') {
            return true;
        }
    }

    $rawJson = (string)($item['raw_json'] ?? '');
    if ($rawJson !== '') {
        $raw = json_decode($rawJson, true);
        if (is_array($raw)) {
            foreach (['affiliateURL', 'service_code', 'floor_code', 'sampleMovieURL'] as $key) {
                if (isset($raw[$key])) {
                    return true;
                }
            }
        }
    }

    return false;
}

function search_item_is_displayable(array $item): bool
{
    if (!search_item_has_product_source($item)) {
        return false;
    }

    if (pcf_item_title($item) === 'タイトル未設定') {
        return false;
    }

    return trim(pcf_item_image($item)) !== '';
}

function search_fetch_items(string $query, int $limit, int $offset): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $like = '%' . addcslashes($query, '\\%_') . '%';
    $params = [':q_title' => $like, ':q_exact_content_id' => $query, ':q_exact_product_id' => $query];
    $whereSql = "(title LIKE :q_title ESCAPE '\\\\' OR content_id = :q_exact_content_id OR product_id = :q_exact_product_id)";
    $sourceWhere = function_exists('items_product_source_where') ? items_product_source_where() : '';
    if ($sourceWhere !== '') {
        $whereSql .= ' AND ' . $sourceWhere;
    }
    $orderSqlCandidates = [
        'view_count DESC, release_date DESC, id DESC',
        'view_count DESC, date_published DESC, id DESC',
        'view_count DESC, id DESC',
        'release_date DESC, id DESC',
        'date_published DESC, id DESC',
        'updated_at DESC, id DESC',
        'id DESC',
    ];

    foreach ($orderSqlCandidates as $orderSql) {
        try {
            $chunkSize = max($limit + 1, 25);
            $cursor = 0;
            $targetCount = $offset + $limit + 1;
            $maxLoops = 30;
            $collected = [];

            for ($i = 0; $i < $maxLoops; $i++) {
                $stmt = db()->prepare('SELECT * FROM items WHERE ' . $whereSql . ' ORDER BY ' . $orderSql . ' LIMIT :l OFFSET :o');
                foreach ($params as $paramName => $paramValue) {
                    $stmt->bindValue($paramName, $paramValue, PDO::PARAM_STR);
                }
                $stmt->bindValue(':l', $chunkSize, PDO::PARAM_INT);
                $stmt->bindValue(':o', $cursor, PDO::PARAM_INT);
                $stmt->execute();
                $chunk = $stmt->fetchAll() ?: [];
                if ($chunk === []) {
                    break;
                }

                $rawFetched = count($chunk);
                $chunk = array_values(array_filter($chunk, static fn(array $row): bool => search_item_is_displayable($row)));
                $collected = dedupe_items_by_key(array_merge($collected, $chunk));
                if (count($collected) >= $targetCount) {
                    break;
                }

                $cursor += $rawFetched;
                if ($rawFetched < $chunkSize) {
                    break;
                }
            }

            return array_slice($collected, $offset, $limit + 1);
        } catch (Throwable) {
        }
    }

    return [];
}

$searchQuery = safe_str($_GET['q'] ?? '', 100);
$page = normalize_int((int)($_GET['page'] ?? 1), 1, 100000);
$limit = (int)(app_config()['pagination']['per_page'] ?? 24);
$offset = ($page - 1) * $limit;
$rows = search_fetch_items($searchQuery, $limit, $offset);
[$items, $hasNext] = paginate_items($rows, $limit);

$title = '検索結果';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('検索結果', $searchQuery !== '' ? '「' . $searchQuery . '」の商品検索結果です。' : 'キーワードを入力して商品を検索できます。'); ?>

<?php if ($searchQuery === ''): ?>
  <?php pcf_render_empty('検索キーワードを入力してください。'); ?>
<?php elseif ($items !== []): ?>
  <section class="rail-section">
    <div class="rail-row rail-row--200 rail-row--wide-thumb">
    <?php foreach ($items as $item): ?>
      <?php pcf_render_item_card(is_array($item) ? $item : [], 200, true); ?>
    <?php endforeach; ?>
    </div>
  </section>
  <nav class="pcf-pagination" aria-label="ページネーション">
    <?php if ($page > 1): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('search.php') . '?' . http_build_query(['q' => $searchQuery, 'page' => $page - 1])) ?>">前へ</a>
    <?php endif; ?>
    <span class="pcf-pagination__link is-current"><?= e((string)$page) ?></span>
    <?php if ($hasNext): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('search.php') . '?' . http_build_query(['q' => $searchQuery, 'page' => $page + 1])) ?>">次へ</a>
    <?php endif; ?>
  </nav>
<?php else: ?>
  <?php pcf_render_empty('検索条件に一致する商品がありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
