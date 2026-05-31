<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function pcf_genre_count_items(int $genreId): int
{
    $genreId = max(1, $genreId);

    try {
        $stmt = db()->prepare(
            'SELECT COUNT(DISTINCT items.id)
             FROM items
             INNER JOIN item_genres ON items.content_id = item_genres.content_id
             WHERE item_genres.genre_id = :id'
        );
        $stmt->execute([':id' => $genreId]);
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
             INNER JOIN genres      ON genres.id          = :id
             INNER JOIN item_genres ON item_genres.dmm_id = genres.dmm_id
             WHERE items.id = item_genres.item_id'
        );
        $stmt->execute([':id' => $genreId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function pcf_genre_display_name(array $row): string
{
    $name = trim((string)($row['name'] ?? ''));
    if ($name !== '' && !pcf_is_noise_name($name)) {
        return $name;
    }

    $genreId = (int)($row['id'] ?? 0);
    if ($genreId > 0) {
        try {
            $stmt = db()->prepare(
                'SELECT genre_name, COUNT(*) AS item_count
                 FROM item_genres
                 WHERE genre_id = :id AND TRIM(COALESCE(genre_name, \'\')) <> \'\'
                 GROUP BY genre_name
                 ORDER BY item_count DESC
                 LIMIT 10'
            );
            $stmt->execute([':id' => $genreId]);
            foreach (($stmt->fetchAll() ?: []) as $candidate) {
                $candidateName = trim((string)($candidate['genre_name'] ?? ''));
                if ($candidateName !== '' && !pcf_is_noise_name($candidateName)) {
                    return $candidateName;
                }
            }
        } catch (Throwable) {
        }
    }

    return $name !== '' ? $name : 'ジャンル詳細';
}

$id = (int)get('id', 0);
$page = max(1, (int)get('page', 1));
$per = 24;
$row = null;
$list = [];
$total = 0;
$pg = paginate(0, $page, $per);
try {
    $row = fetch_genre($id);
    if ($row !== null) {
        $total = pcf_genre_count_items((int)$row['id']);
        $pg = paginate($total, $page, $per);
        $list = dedupe_items_by_key(fetch_items_by_genre((int)$row['id'], (int)$pg['perPage'], (int)$pg['offset']));
    }
} catch (Throwable) {
    $row = null;
    $list = [];
    $total = 0;
    $pg = paginate(0, $page, $per);
}
if ($row === null) {
    http_response_code(404);
    exit('not found');
}

$genreName = pcf_genre_display_name($row);

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

    $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN item_genres ig ON i.content_id = ig.content_id WHERE ig.genre_id = :genre_id AND pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
    $rankingStmt->execute([':genre_id' => (int)$row['id'], ':period_from' => $periodFrom]);
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

        $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN genres g ON g.id = :genre_id INNER JOIN item_genres ig ON ig.dmm_id = g.dmm_id WHERE i.id = ig.item_id AND pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
        $rankingStmt->execute([':genre_id' => (int)$row['id'], ':period_from' => $periodFrom]);
        $accessRankingRows = $rankingStmt->fetchAll() ?: [];
    } catch (Throwable) {
        $accessRankingRows = [];
    }
}

$title = $genreName;
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'ジャンル一覧', 'url' => public_url('genres.php')],
    ['label' => $genreName],
]); ?>

<?php pcf_render_hero($genreName); ?>

<h2 class="pcf-section-title"><?= e($genreName) ?>一覧</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('genre.php'), ['id' => (int)$row['id']]); ?>
<?php else: ?>
  <?php pcf_render_empty('このジャンルに紐づく商品はまだありません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title"><?= e($genreName) ?>アクセスランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php
      $tabQuery = ['id' => (int)$row['id'], 'rank_period' => (string)$tabKey];
      $tabUrl = public_url(basename(__FILE__)) . '?' . http_build_query($tabQuery) . '#access-ranking';
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
