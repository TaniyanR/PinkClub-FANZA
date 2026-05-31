<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function pcf_series_count_items(int $seriesId): int
{
    $seriesId = max(1, $seriesId);
    $sourceWhere = function_exists('items_product_source_where') ? items_product_source_where('items') : '';
    $sourceSql = $sourceWhere !== '' ? ' AND ' . $sourceWhere : '';

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(DISTINCT items.id)
             FROM items
             INNER JOIN item_series ON items.content_id = item_series.content_id
             WHERE item_series.series_id = :id' . $sourceSql
        );
        $stmt->execute([':id' => $seriesId]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            return $count;
        }
    } catch (Throwable) {
    }

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(DISTINCT items.id)
             FROM items
             INNER JOIN series_master ON series_master.id = :id
             INNER JOIN item_series ON item_series.dmm_id = series_master.dmm_id
             WHERE items.id = item_series.item_id' . $sourceSql
        );
        $stmt->execute([':id' => $seriesId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function pcf_fetch_series_items(int $seriesId, int $limit, int $offset): array
{
    $seriesId = max(1, $seriesId);
    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);
    $sourceWhere = function_exists('items_product_source_where') ? items_product_source_where('items') : '';
    $sourceSql = $sourceWhere !== '' ? ' AND ' . $sourceWhere : '';

    try {
        $stmt = db()->prepare(
            'SELECT DISTINCT items.*
             FROM items
             INNER JOIN item_series ON items.content_id = item_series.content_id
             WHERE item_series.series_id = :id' . $sourceSql . '
             ORDER BY date_published DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':id', $seriesId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable) {
    }

    try {
        $stmt = db()->prepare(
            'SELECT DISTINCT items.*
             FROM items
             INNER JOIN series_master ON series_master.id = :id
             INNER JOIN item_series ON item_series.dmm_id = series_master.dmm_id
             WHERE items.id = item_series.item_id' . $sourceSql . '
             ORDER BY items.release_date DESC, items.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':id', $seriesId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

$id = (int)get('id', 0);
$series = null;
$items = [];
$page = max(1, (int)get('page', 1));
$per = 24;
$total = 0;
$pg = paginate(0, $page, $per);
try {
    $series = fetch_series_one($id);
    if ($series !== null) {
        $total = pcf_series_count_items((int)$series['id']);
        $pg = paginate($total, $page, $per);
        $items = dedupe_items_by_key(pcf_fetch_series_items((int)$series['id'], (int)$pg['perPage'], (int)$pg['offset']));
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
$accessRankingRows = [];
$rankingSourceWhere = function_exists('items_product_source_where') ? items_product_source_where('i') : '';
$rankingSourceSql = $rankingSourceWhere !== '' ? ' AND ' . $rankingSourceWhere : '';
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

    $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(DISTINCT pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN item_genres ig ON i.content_id = ig.content_id WHERE ig.genre_id IN (SELECT DISTINCT igs.genre_id FROM item_series isr INNER JOIN items si ON si.content_id = isr.content_id INNER JOIN item_genres igs ON igs.content_id = si.content_id WHERE isr.series_id = :series_id)' . $rankingSourceSql . ' AND pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
    $rankingStmt->execute([':series_id' => (int)$series['id'], ':period_from' => $periodFrom]);
    $accessRankingRows = $rankingStmt->fetchAll() ?: [];
} catch (Throwable) {
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

        $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(DISTINCT pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN item_genres ig ON i.id = ig.item_id INNER JOIN genres g ON g.dmm_id = ig.dmm_id WHERE g.id IN (SELECT DISTINCT sg.id FROM series_master sm INNER JOIN item_series isr ON isr.dmm_id = sm.dmm_id INNER JOIN items si ON si.id = isr.item_id INNER JOIN item_genres sig ON sig.item_id = si.id INNER JOIN genres sg ON sg.dmm_id = sig.dmm_id WHERE sm.id = :series_id)' . $rankingSourceSql . ' AND pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
        $rankingStmt->execute([':series_id' => (int)$series['id'], ':period_from' => $periodFrom]);
        $accessRankingRows = $rankingStmt->fetchAll() ?: [];
    } catch (Throwable) {
        $accessRankingRows = [];
    }
}

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
  <?php pcf_render_pagination($pg, public_url(basename((string)($_SERVER['SCRIPT_NAME'] ?? 'series_detail.php'))), ['id' => (int)$series['id']]); ?>
<?php else: ?>
  <?php pcf_render_empty('このシリーズの作品はまだありません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">ジャンルアクセスランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php
      $tabQuery = ['id' => (int)$series['id'], 'rank_period' => (string)$tabKey];
      $tabUrl = public_url(basename((string)($_SERVER['SCRIPT_NAME'] ?? 'series_detail.php'))) . '?' . http_build_query($tabQuery) . '#access-ranking';
      ?>
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
    <?php pcf_render_empty('ジャンルアクセスランキングのデータがありません。'); ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
