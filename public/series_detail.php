<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function pcf_series_access_period_from(string $period): string
{
    if ($period === 'weekly') {
        return date('Y-m-d H:i:s', strtotime('-7 days'));
    }
    if ($period === 'monthly') {
        return date('Y-m-d H:i:s', strtotime('-1 month'));
    }
    if ($period === 'yearly') {
        return date('Y-m-d H:i:s', strtotime('-1 year'));
    }
    return date('Y-m-d H:i:s', strtotime('-24 hours'));
}


function pcf_sync_series_items_from_api(array $series, int $page, int $per): array
{
    $dmmId = trim((string)($series['dmm_id'] ?? ''));
    if ($dmmId === '' || str_starts_with($dmmId, 'name:')) {
        return ['total_count' => 0, 'content_ids' => []];
    }

    try {
        $settings = settings_get();
        $required = max(1, $page) * max(1, $per);
        $result = dmm_sync_service()->syncItemsByParams(
            (string)($settings['site'] ?? 'FANZA'),
            (string)($settings['service'] ?? 'digital'),
            (string)($settings['floor'] ?? 'videoa'),
            $required,
            ['sort' => 'date', 'article' => 'series', 'article_id' => $dmmId]
        );
        $contentIds = array_values(array_filter(array_map('strval', is_array($result['content_ids'] ?? null) ? $result['content_ids'] : []), static fn(string $value): bool => trim($value) !== ''));
        return ['total_count' => max(0, (int)($result['total_count'] ?? 0)), 'content_ids' => $contentIds];
    } catch (Throwable $e) {
        error_log('series_detail.php ItemList series sync failed: ' . $e->getMessage());
        return ['total_count' => 0, 'content_ids' => []];
    }
}

function pcf_fetch_items_by_content_ids(array $contentIds): array
{
    $contentIds = array_values(array_unique(array_filter(array_map('strval', $contentIds), static fn(string $value): bool => trim($value) !== '')));
    if ($contentIds === []) {
        return [];
    }

    try {
        $placeholders = [];
        $params = [];
        foreach ($contentIds as $index => $contentId) {
            $key = ':cid' . $index;
            $placeholders[] = $key;
            $params[$key] = $contentId;
        }
        $sourceWhere = function_exists('items_product_source_where') ? items_product_source_where('items') : '';
        $sourceSql = $sourceWhere !== '' ? ' AND ' . $sourceWhere : '';
        $stmt = db()->prepare('SELECT * FROM items WHERE content_id IN (' . implode(',', $placeholders) . ')' . $sourceSql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
        $byContentId = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $byContentId[(string)($row['content_id'] ?? '')] = $row;
            }
        }
        $ordered = [];
        foreach ($contentIds as $contentId) {
            if (isset($byContentId[$contentId])) {
                $ordered[] = $byContentId[$contentId];
            }
        }
        return $ordered;
    } catch (Throwable) {
        return [];
    }
}

function pcf_fetch_series_genre_access_ranking(int $seriesId, string $periodFrom): array
{
    $seriesId = max(1, $seriesId);
    $sourceWhere = function_exists('items_product_source_where') ? items_product_source_where('ranking_items') : '';
    $sourceSql = $sourceWhere !== '' ? ' AND ' . $sourceWhere : '';

    try {
        $stmt = db()->prepare(
            'SELECT ranking_items.id, ranking_items.content_id, ranking_items.title, COUNT(DISTINCT pv.id) AS access_count
             FROM page_views pv
             INNER JOIN items ranking_items ON ranking_items.id = pv.item_id
             INNER JOIN item_genres ranking_genres ON ranking_genres.item_id = ranking_items.id
             WHERE ranking_genres.dmm_id IN (
                 SELECT DISTINCT series_genres.dmm_id
                 FROM series_master
                 INNER JOIN item_series series_rel ON series_rel.dmm_id = series_master.dmm_id
                 INNER JOIN item_genres series_genres ON series_genres.item_id = series_rel.item_id
                 WHERE series_master.id = :series_id AND TRIM(COALESCE(series_genres.dmm_id, \'\')) <> \'\'
             )' . $sourceSql . '
             AND pv.viewed_at >= :period_from
             GROUP BY ranking_items.id, ranking_items.content_id, ranking_items.title
             ORDER BY access_count DESC, ranking_items.id DESC
             LIMIT 200'
        );
        $stmt->execute([':series_id' => $seriesId, ':period_from' => $periodFrom]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

$id = (int)get('id', 0);
$page = max(1, (int)get('page', 1));
$per = 24;
$series = null;
$items = [];
$total = 0;
$pg = paginate(0, $page, $per);

try {
    $series = fetch_series_one($id);
    if ($series !== null) {
        $apiResult = pcf_sync_series_items_from_api($series, $page, $per);
        $apiTotal = (int)($apiResult['total_count'] ?? 0);
        $apiContentIds = is_array($apiResult['content_ids'] ?? null) ? $apiResult['content_ids'] : [];
        $total = function_exists('count_items_by_series') ? count_items_by_series((int)$series['id']) : 0;
        if ($apiTotal > 0) {
            $total = $apiTotal;
        }
        $pg = paginate($total, $page, $per);
        if ($apiContentIds !== []) {
            $pageContentIds = array_slice($apiContentIds, (int)$pg['offset'], (int)$pg['perPage']);
            $items = dedupe_items_by_key(pcf_fetch_items_by_content_ids($pageContentIds));
        }
        if ($items === []) {
            $items = dedupe_items_by_key(fetch_items_by_series((int)$series['id'], (int)$pg['perPage'], (int)$pg['offset']));
        }
    }
} catch (Throwable) {
    $series = null;
    $items = [];
    $total = 0;
    $pg = paginate(0, $page, $per);
}

if ($series === null) {
    http_response_code(404);
    exit('not found');
}

$seriesName = (string)($series['name'] ?? 'シリーズ詳細');
$accessRankingPeriod = trim((string)get('rank_period', 'daily'));
$accessRankingTabs = [
    'daily' => ['label' => '24時間'],
    'weekly' => ['label' => '週間'],
    'monthly' => ['label' => '月間'],
    'yearly' => ['label' => '年間'],
];
if (!isset($accessRankingTabs[$accessRankingPeriod])) {
    $accessRankingPeriod = 'daily';
}
$accessRankingRows = pcf_fetch_series_genre_access_ranking((int)$series['id'], pcf_series_access_period_from($accessRankingPeriod));

$title = $seriesName;
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'シリーズ一覧', 'url' => public_url('series_list.php')],
    ['label' => $seriesName],
]); ?>
<?php pcf_render_hero($seriesName); ?>

<h2 class="pcf-section-title"><?= e($seriesName) ?>一覧</h2>
<?php if ($items !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($items as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('series_detail.php'), ['id' => (int)$series['id']]); ?>
<?php else: ?>
  <?php pcf_render_empty('このシリーズの作品はまだありません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">ジャンルアクセスランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php $tabUrl = public_url('series_detail.php') . '?' . http_build_query(['id' => (int)$series['id'], 'rank_period' => (string)$tabKey]) . '#access-ranking'; ?>
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
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:left;"><a href="<?= e(public_url('item.php') . '?id=' . rawurlencode((string)($rankingRow['id'] ?? ''))) ?>"><?= e((string)($rankingRow['title'] ?? '')) ?></a></td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)((int)($rankingRow['access_count'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php pcf_render_empty('ジャンルアクセスランキングのデータがありません。'); ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
