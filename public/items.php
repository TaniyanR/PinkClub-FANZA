<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';
require_once __DIR__ . '/../lib/repository.php';

function dedupe_items_for_listing(array $items): array
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

        $score = 0;
        if (trim((string)($item['title'] ?? '')) !== '') {
            $score += 2;
        }
        if (trim((string)($item['image_small'] ?? '')) !== '' || trim((string)($item['image_large'] ?? '')) !== '' || trim((string)($item['image_list'] ?? '')) !== '') {
            $score += 2;
        }
        if (trim((string)($item['affiliate_url'] ?? '')) !== '') {
            $score += 1;
        }

        if ($key !== '' && isset($seen[$key])) {
            $index = (int)$seen[$key];
            $existing = $result[$index] ?? [];
            $existingScore = 0;
            if (trim((string)($existing['title'] ?? '')) !== '') {
                $existingScore += 2;
            }
            if (trim((string)($existing['image_small'] ?? '')) !== '' || trim((string)($existing['image_large'] ?? '')) !== '' || trim((string)($existing['image_list'] ?? '')) !== '') {
                $existingScore += 2;
            }
            if (trim((string)($existing['affiliate_url'] ?? '')) !== '') {
                $existingScore += 1;
            }
            if ($score > $existingScore) {
                $result[$index] = $item;
            }
            continue;
        }
        if ($key !== '') {
            $seen[$key] = count($result);
        }
        $result[] = $item;
    }
    return $result;
}


function search_like_escape_for_listing(string $value): string
{
    return strtr($value, ["\\" => "\\\\", '%' => '\\%', '_' => '\\_']);
}

function item_search_sql_parts(array $terms): array
{
    $joins = [];
    $searchFields = [
        'i.title',
        'i.content_id',
        'i.product_id',
        'i.service_name',
        'i.floor_name',
        'i.category_name',
        'i.volume',
        'i.price_min_text',
        'i.list_price_text',
    ];
    $relationTables = [
        ['table' => 'item_actresses', 'column' => 'actress_name', 'alias' => 'sia'],
        ['table' => 'item_genres', 'column' => 'genre_name', 'alias' => 'sig'],
        ['table' => 'item_makers', 'column' => 'maker_name', 'alias' => 'sim'],
        ['table' => 'item_series', 'column' => 'series_name', 'alias' => 'sis'],
        ['table' => 'item_authors', 'column' => 'author_name', 'alias' => 'siau'],
        ['table' => 'item_labels', 'column' => 'label_name', 'alias' => 'sil'],
        ['table' => 'item_actors', 'column' => 'actor_name', 'alias' => 'siac'],
    ];

    foreach ($relationTables as $relation) {
        if (!function_exists('db_table_exists') || !db_table_exists((string)$relation['table'])) {
            continue;
        }
        $alias = (string)$relation['alias'];
        $table = (string)$relation['table'];
        $column = (string)$relation['column'];
        $joins[] = 'LEFT JOIN (SELECT item_id, GROUP_CONCAT(' . $column . ' SEPARATOR " ") AS names FROM ' . $table . ' GROUP BY item_id) ' . $alias . ' ON ' . $alias . '.item_id = i.id';
        $searchFields[] = 'COALESCE(' . $alias . '.names, "")';
    }

    $searchText = 'CONCAT_WS(" ", ' . implode(', ', $searchFields) . ')';
    $where = [];
    $params = [];
    foreach ($terms as $index => $term) {
        $param = ':kw' . $index;
        $where[] = $searchText . ' LIKE ' . $param . " ESCAPE '\\\\'";
        $params[$param] = '%' . search_like_escape_for_listing((string)$term) . '%';
    }

    return [$joins, $where, $params, $searchText];
}

function fetch_search_items_for_listing(string $keyword, int $limit, int $offset): array
{
    $terms = preg_split('/\s+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($terms) || $terms === []) {
        return [];
    }
    $terms = array_slice(array_values(array_unique($terms)), 0, 5);
    [$joins, $where, $params, $searchText] = item_search_sql_parts($terms);

    $sql = 'SELECT i.* FROM items i ' . implode(' ', $joins)
        . ' WHERE ' . implode(' AND ', $where)
        . ' ORDER BY CASE'
        . ' WHEN i.title = :exact THEN 0'
        . ' WHEN i.title LIKE :prefix ESCAPE \'\\\\\' THEN 1'
        . ' WHEN i.title LIKE :phrase ESCAPE \'\\\\\' THEN 2'
        . ' WHEN ' . $searchText . ' LIKE :phrase_text ESCAPE \'\\\\\' THEN 3'
        . ' ELSE 4 END, i.view_count DESC, i.release_date DESC, i.id DESC LIMIT :l OFFSET :o';

    $stmt = db()->prepare($sql);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value, PDO::PARAM_STR);
    }
    $escapedKeyword = search_like_escape_for_listing($keyword);
    $stmt->bindValue(':exact', $keyword, PDO::PARAM_STR);
    $stmt->bindValue(':prefix', $escapedKeyword . '%', PDO::PARAM_STR);
    $stmt->bindValue(':phrase', '%' . $escapedKeyword . '%', PDO::PARAM_STR);
    $stmt->bindValue(':phrase_text', '%' . $escapedKeyword . '%', PDO::PARAM_STR);
    $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function count_search_items_for_listing(string $keyword): int
{
    $terms = preg_split('/\s+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($terms) || $terms === []) {
        return 0;
    }
    $terms = array_slice(array_values(array_unique($terms)), 0, 5);
    [$joins, $where, $params] = item_search_sql_parts($terms);

    $stmt = db()->prepare('SELECT COUNT(DISTINCT i.id) FROM items i ' . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where));
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int)$stmt->fetchColumn();
}

function is_displayable_item_for_listing(array $item): bool
{
    $title = trim((string)($item['title'] ?? ''));
    $raw = [];
    $rawJson = (string)($item['raw_json'] ?? '');
    if ($rawJson !== '') {
        $decoded = json_decode($rawJson, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        }
    }
    if ($title === '' || $title === 'タイトル未設定') {
        $title = trim((string)($raw['title'] ?? $raw['iteminfo']['title'] ?? ''));
        if ($title === '') {
            return false;
        }
    }

    foreach (['image_small', 'image_large', 'image_list'] as $key) {
        if (trim((string)($item[$key] ?? '')) !== '') {
            return true;
        }
    }

    if ($raw !== []) {
        foreach (['small', 'large', 'list'] as $imageKey) {
            if (trim((string)($raw['imageURL'][$imageKey] ?? '')) !== '') {
                return true;
            }
        }
    }

    return false;
}

$page = max(1, (int)get('page', 1));
$per = app_config()['pagination']['per_page'] ?? 24;
$searchQuery = safe_str(get('q', ''), 100);
$isSearch = $searchQuery !== '';
$total = 0;
$rows = [];

if ($isSearch) {
    try {
        $total = count_search_items_for_listing($searchQuery);
    } catch (Throwable) {
        $total = 0;
    }

    $pg = paginate($total, $page, (int)$per);

    try {
        $chunkSize = (int)$pg['perPage'] + 1;
        $cursor = (int)$pg['offset'];
        $maxLoops = 6;
        $collected = [];

        for ($i = 0; $i < $maxLoops; $i++) {
            $chunk = fetch_search_items_for_listing($searchQuery, $chunkSize, $cursor);
            if ($chunk === []) {
                break;
            }

            $rawFetched = count($chunk);
            $chunk = array_values(array_filter($chunk, static fn(array $row): bool => is_displayable_item_for_listing($row)));
            $collected = dedupe_items_for_listing(array_merge($collected, $chunk));
            if (count($collected) > (int)$pg['perPage']) {
                break;
            }

            $cursor += $rawFetched;
            if ($rawFetched < $chunkSize) {
                break;
            }
        }

        $rows = array_slice($collected, 0, (int)$pg['perPage']);
    } catch (Throwable) {
        $rows = [];
    }
} else {
    try {
        $total = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
    } catch (Throwable) {
        $total = 0;
    }

    $pg = paginate($total, $page, (int)$per);

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
            $chunkSize = (int)$pg['perPage'] + 1;
            $cursor = (int)$pg['offset'];
            $maxLoops = 6;
            $collected = [];

            for ($i = 0; $i < $maxLoops; $i++) {
                $stmt = db()->prepare('SELECT * FROM items ORDER BY ' . $orderSql . ' LIMIT :l OFFSET :o');
                $stmt->bindValue(':l', $chunkSize, PDO::PARAM_INT);
                $stmt->bindValue(':o', $cursor, PDO::PARAM_INT);
                $stmt->execute();
                $chunk = $stmt->fetchAll() ?: [];
                if ($chunk === []) {
                    break;
                }

                $rawFetched = count($chunk);
                $chunk = array_values(array_filter($chunk, static fn(array $row): bool => is_displayable_item_for_listing($row)));
                $collected = dedupe_items_for_listing(array_merge($collected, $chunk));
                if (count($collected) > (int)$pg['perPage']) {
                    break;
                }

                $cursor += $rawFetched;
                if ($rawFetched < $chunkSize) {
                    break;
                }
            }

            $rows = array_slice($collected, 0, (int)$pg['perPage']);
            break;
        } catch (Throwable) {
            $rows = [];
        }
    }
}

$accessRankingPeriod = trim((string)get('rank_period', 'daily'));
$accessRankingTabs = [
    'daily' => ['label' => '24時間', 'where' => 'pv.viewed_at >= (NOW() - INTERVAL 1 DAY)'],
    'weekly' => ['label' => '週間', 'where' => 'pv.viewed_at >= (NOW() - INTERVAL 7 DAY)'],
    'monthly' => ['label' => '月間', 'where' => 'pv.viewed_at >= (NOW() - INTERVAL 1 MONTH)'],
    'yearly' => ['label' => '年間', 'where' => 'pv.viewed_at >= (NOW() - INTERVAL 1 YEAR)'],
];
if (!isset($accessRankingTabs[$accessRankingPeriod])) {
    $accessRankingPeriod = 'daily';
}
$accessRankingRows = [];
if (!$isSearch) {
    try {
        $periodFrom = null;
        if ($accessRankingPeriod === 'daily') {
            $periodFrom = date('Y-m-d H:i:s', strtotime('-24 hours'));
        } elseif ($accessRankingPeriod === 'weekly') {
            $periodFrom = date('Y-m-d H:i:s', strtotime('-7 days'));
        } elseif ($accessRankingPeriod === 'monthly') {
            $periodFrom = date('Y-m-d H:i:s', strtotime('-1 month'));
        } elseif ($accessRankingPeriod === 'yearly') {
            $periodFrom = date('Y-m-d H:i:s', strtotime('-1 year'));
        }

        if ($periodFrom === null) {
            $periodFrom = date('Y-m-d H:i:s', strtotime('-24 hours'));
        }

        $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id WHERE pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
        $rankingStmt->execute([':period_from' => $periodFrom]);
        $accessRankingRows = $rankingStmt->fetchAll() ?: [];
    } catch (Throwable) {
        $accessRankingRows = [];
    }
}

$title = $isSearch ? '検索結果: ' . $searchQuery : '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero($isSearch ? '検索結果: ' . $searchQuery : '商品一覧', $isSearch ? '入力キーワードに一致する作品のみを表示しています。' : '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section class="rail-section">
    <div class="rail-row rail-row--200 rail-row--wide-thumb">
    <?php foreach ($rows as $r): ?>
      <?php
      $itemRow = is_array($r) ? $r : [];
      $contentId = trim((string)($itemRow['content_id'] ?? ''));
      if ($contentId !== '' && function_exists('fetch_item_by_content_id')) {
          try {
              $resolved = fetch_item_by_content_id($contentId);
              if (is_array($resolved)) {
                  $itemRow = array_merge($itemRow, $resolved);
              }
          } catch (Throwable) {
          }
      }
      pcf_render_item_card($itemRow, 200, true);
      ?>
    <?php endforeach; ?>
    </div>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php'), $isSearch ? ['q' => $searchQuery] : []); ?>
<?php else: ?>
  <?php pcf_render_empty($isSearch ? '検索条件に一致する作品が見つかりませんでした。' : '商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php if (!$isSearch): ?>
<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">アクセスランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php $tabUrl = public_url(basename(__FILE__)) . '?rank_period=' . rawurlencode((string)$tabKey) . '#access-ranking'; ?>
      <?php $tabStyle = $accessRankingPeriod === $tabKey ? 'display:inline-block; padding:6px 12px; border:1px solid #0b5ed7; border-radius:6px; background:#0b5ed7; color:#fff; font-weight:700; text-decoration:none;' : 'display:inline-block; padding:6px 12px; border:1px solid #0b5ed7; border-radius:6px; background:#fff; color:#0b5ed7; font-weight:700; text-decoration:none;'; ?>
      <a href="<?= e($tabUrl) ?>" style="<?= e($tabStyle) ?>"><?= e((string)$tabConfig['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($accessRankingRows !== []): ?>
    <div style="max-height:800px; overflow-y:auto; border:1px solid #ddd;">
      <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <thead>
          <tr>
            <th style="width:80px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">順位</th>
            <th style="width:auto; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">作品タイトル</th>
            <th style="width:120px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">アクセス数</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessRankingRows as $index => $rankingRow): ?>
            <tr>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)($index + 1)) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:left;">
                <?php
                $rankingItemUrl = public_url('item.php') . '?id=' . rawurlencode((string)($rankingRow['id'] ?? ''));
                ?>
                <a href="<?= e($rankingItemUrl) ?>"><?= e((string)($rankingRow['title'] ?? '')) ?></a>
              </td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)((int)($rankingRow['access_count'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php pcf_render_empty('アクセスランキングのデータがありません。'); ?>
  <?php endif; ?>
</section>

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>