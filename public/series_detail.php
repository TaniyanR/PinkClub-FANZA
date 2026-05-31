<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function pcf_series_item_source_sql(string $alias): string
{
    $sourceWhere = function_exists('items_product_source_where') ? items_product_source_where($alias) : '';
    return $sourceWhere !== '' ? ' AND ' . $sourceWhere : '';
}

function pcf_series_use_name_lookup(string $seriesDmmId): bool
{
    $seriesDmmId = trim($seriesDmmId);
    return $seriesDmmId === '' || str_starts_with($seriesDmmId, 'name:');
}

function pcf_fetch_series_detail_row(int $seriesId): ?array
{
    $seriesId = max(1, $seriesId);

    try {
        $stmt = db()->prepare('SELECT * FROM series_master WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $seriesId]);
        $row = $stmt->fetch();
        if (is_array($row)) {
            return $row;
        }
    } catch (Throwable) {
    }

    try {
        $stmt = db()->prepare('SELECT * FROM series WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $seriesId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function pcf_series_count_items(string $seriesName, string $seriesDmmId): int
{
    $seriesName = trim($seriesName);
    $seriesDmmId = trim($seriesDmmId);
    if ($seriesName === '' && $seriesDmmId === '') {
        return 0;
    }

    if ($seriesDmmId !== '' && !pcf_series_use_name_lookup($seriesDmmId)) {
        try {
            $stmt = db()->prepare(
                'SELECT COUNT(DISTINCT items.id)
                 FROM items
                 INNER JOIN item_series ON items.id = item_series.item_id
                 WHERE item_series.dmm_id = :dmm_id' . pcf_series_item_source_sql('items')
            );
            $stmt->execute([':dmm_id' => $seriesDmmId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    if ($seriesName !== '') {
        try {
            $stmt = db()->prepare(
                'SELECT COUNT(DISTINCT items.id)
                 FROM items
                 INNER JOIN item_series ON items.id = item_series.item_id
                 WHERE TRIM(item_series.series_name) = :name' . pcf_series_item_source_sql('items')
            );
            $stmt->execute([':name' => $seriesName]);
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
                 INNER JOIN item_series ON items.content_id = item_series.content_id
                 WHERE TRIM(item_series.series_name) = :name' . pcf_series_item_source_sql('items')
            );
            $stmt->execute([':name' => $seriesName]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    return 0;
}

function pcf_fetch_series_items(string $seriesName, string $seriesDmmId, int $limit, int $offset): array
{
    $seriesName = trim($seriesName);
    $seriesDmmId = trim($seriesDmmId);
    $limit = normalize_int($limit, 1, 100);
    $offset = max(0, $offset);

    if ($seriesDmmId !== '' && !pcf_series_use_name_lookup($seriesDmmId)) {
        try {
            $stmt = db()->prepare(
                'SELECT DISTINCT items.*
                 FROM items
                 INNER JOIN item_series ON items.id = item_series.item_id
                 WHERE item_series.dmm_id = :dmm_id' . pcf_series_item_source_sql('items') . '
                 ORDER BY items.release_date DESC, items.id DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':dmm_id', $seriesDmmId, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    if ($seriesName !== '') {
        try {
            $stmt = db()->prepare(
                'SELECT DISTINCT items.*
                 FROM items
                 INNER JOIN item_series ON items.id = item_series.item_id
                 WHERE TRIM(item_series.series_name) = :name' . pcf_series_item_source_sql('items') . '
                 ORDER BY items.release_date DESC, items.id DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':name', $seriesName, PDO::PARAM_STR);
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
                 INNER JOIN item_series ON items.content_id = item_series.content_id
                 WHERE TRIM(item_series.series_name) = :name' . pcf_series_item_source_sql('items') . '
                 ORDER BY items.release_date DESC, items.id DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':name', $seriesName, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    return [];
}

function pcf_fetch_series_genre_ranking(string $seriesName, string $seriesDmmId, string $periodFrom): array
{
    $seriesName = trim($seriesName);
    $seriesDmmId = trim($seriesDmmId);

    if ($seriesDmmId !== '' && !pcf_series_use_name_lookup($seriesDmmId)) {
        try {
            $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(DISTINCT pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN item_genres ig ON ig.item_id = i.id WHERE ig.dmm_id IN (SELECT DISTINCT sig.dmm_id FROM item_series isr INNER JOIN item_genres sig ON sig.item_id = isr.item_id WHERE isr.dmm_id = :dmm_id AND TRIM(COALESCE(sig.dmm_id, "")) <> "")' . pcf_series_item_source_sql('i') . ' AND pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
            $rankingStmt->execute([':dmm_id' => $seriesDmmId, ':period_from' => $periodFrom]);
            return $rankingStmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    if ($seriesName !== '') {
        try {
            $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(DISTINCT pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN item_genres ig ON ig.item_id = i.id WHERE ig.dmm_id IN (SELECT DISTINCT sig.dmm_id FROM item_series isr INNER JOIN item_genres sig ON sig.item_id = isr.item_id WHERE TRIM(isr.series_name) = :name AND TRIM(COALESCE(sig.dmm_id, "")) <> "")' . pcf_series_item_source_sql('i') . ' AND pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
            $rankingStmt->execute([':name' => $seriesName, ':period_from' => $periodFrom]);
            $rows = $rankingStmt->fetchAll() ?: [];
            if ($rows !== []) {
                return $rows;
            }
        } catch (Throwable) {
        }

        try {
            $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(DISTINCT pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN item_genres ig ON ig.content_id = i.content_id WHERE ig.genre_id IN (SELECT DISTINCT sig.genre_id FROM item_series isr INNER JOIN item_genres sig ON sig.content_id = isr.content_id WHERE TRIM(isr.series_name) = :name)' . pcf_series_item_source_sql('i') . ' AND pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
            $rankingStmt->execute([':name' => $seriesName, ':period_from' => $periodFrom]);
            return $rankingStmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    return [];
}

$id = (int)get('id', 0);
$series = null;
$seriesDmmId = '';
$items = [];
$page = max(1, (int)get('page', 1));
$per = 24;
$total = 0;
$pg = paginate(0, $page, $per);
try {
    $series = pcf_fetch_series_detail_row($id);
    if ($series !== null) {
        $seriesName = (string)($series['name'] ?? '');
        $seriesDmmId = (string)($series['dmm_id'] ?? '');
        $total = pcf_series_count_items($seriesName, $seriesDmmId);
        $pg = paginate($total, $page, $per);
        $items = dedupe_items_by_key(pcf_fetch_series_items($seriesName, $seriesDmmId, (int)$pg['perPage'], (int)$pg['offset']));
    }
} catch (Throwable) {
    $series = null;
    $seriesDmmId = '';
    $items = [];
    $total = 0;
    $pg = paginate(0, $page, $per);
}
if ($series === null) {
    http_response_code(404);
    exit('not found');
}

$seriesName = (string)($series['name'] ?? 'シリーズ詳細');
$seriesDmmId = (string)($series['dmm_id'] ?? $seriesDmmId);

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

    $accessRankingRows = pcf_fetch_series_genre_ranking($seriesName, $seriesDmmId, $periodFrom);
} catch (Throwable) {
    $accessRankingRows = [];
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
