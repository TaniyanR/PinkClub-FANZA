<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$id = (int)get('id', 0);
$row = null;
$list = [];
$makerPage = max(1, (int)get('page', 1));
$limit = 24;
$offset = ($makerPage - 1) * $limit;
$hasNext = false;
try {
    $row = fetch_maker($id);
    if ($row !== null) {
        $rows = dedupe_items_by_key(fetch_items_by_maker((int)$row['id'], $limit + 1, $offset));
        [$list, $hasNext] = paginate_items($rows, $limit);
    }
} catch (Throwable) {
    $row = null;
    $list = [];
}
$makerName = trim((string)($row['name'] ?? ''));
foreach ([
    "SELECT maker_name FROM item_makers WHERE maker_id = :id AND TRIM(COALESCE(maker_name, '')) <> '' GROUP BY maker_name ORDER BY COUNT(*) DESC, maker_name ASC LIMIT 1",
    "SELECT im.maker_name FROM item_makers im INNER JOIN makers m ON m.id = :id AND im.dmm_id = m.dmm_id WHERE TRIM(COALESCE(im.maker_name, '')) <> '' GROUP BY im.maker_name ORDER BY COUNT(*) DESC, im.maker_name ASC LIMIT 1",
] as $makerNameSql) {
    try {
        $makerNameStmt = db()->prepare($makerNameSql);
        $makerNameStmt->execute([':id' => $id]);
        $makerNameCandidate = trim((string)($makerNameStmt->fetchColumn() ?: ''));
        if ($makerNameCandidate !== '' && !pcf_is_noise_name($makerNameCandidate)) {
            $makerName = $makerNameCandidate;
            break;
        }
    } catch (Throwable) {
    }
}
$makerNameIsMutualLink = false;
if ($makerName !== '') {
    try {
        $mutualLinkStmt = db()->prepare('SELECT id FROM mutual_links WHERE site_name = :name LIMIT 1');
        $mutualLinkStmt->execute([':name' => $makerName]);
        $makerNameIsMutualLink = (bool)$mutualLinkStmt->fetchColumn();
    } catch (Throwable) {
        $makerNameIsMutualLink = false;
    }
}
if ($row === null || $makerName === '' || pcf_is_noise_name($makerName) || $makerNameIsMutualLink) {
    http_response_code(404);
    exit('not found');
}

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
foreach ([
    'SELECT g.id, g.name, COUNT(DISTINCT pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN item_genres ig ON ig.content_id = i.content_id INNER JOIN genres g ON g.id = ig.genre_id WHERE pv.viewed_at >= :period_from GROUP BY g.id, g.name ORDER BY access_count DESC, g.id DESC LIMIT 200',
    'SELECT g.id, g.name, COUNT(DISTINCT pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id INNER JOIN item_genres ig ON ig.item_id = i.id INNER JOIN genres g ON g.dmm_id = ig.dmm_id WHERE pv.viewed_at >= :period_from GROUP BY g.id, g.name ORDER BY access_count DESC, g.id DESC LIMIT 200',
] as $rankingSql) {
    try {
        $rankingStmt = db()->prepare($rankingSql);
        $rankingStmt->execute([':period_from' => $periodFrom]);
        $accessRankingRows = $rankingStmt->fetchAll() ?: [];
        if ($accessRankingRows !== []) {
            break;
        }
    } catch (Throwable) {
        $accessRankingRows = [];
    }
}

$title = $makerName;
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'メーカー一覧', 'url' => public_url('makers.php')],
    ['label' => $makerName],
]); ?>

<section class="pcf-hero">
  <h1 class="pcf-hero__title"><?= e($makerName) ?></h1>
  <?php if (!empty($row['ruby'])): ?><p class="pcf-hero__subtitle">読み: <?= e((string)$row['ruby']) ?></p><?php endif; ?>
</section>

<h2 class="pcf-section-title"><?= e($makerName) ?>一覧</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
  <nav class="pcf-pagination" aria-label="ページネーション">
    <?php if ($makerPage > 1): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('maker.php') . '?' . http_build_query(['id' => $id, 'page' => $makerPage - 1])) ?>">前へ</a>
    <?php endif; ?>
    <span class="pcf-pagination__link is-current"><?= e((string)$makerPage) ?></span>
    <?php if ($hasNext): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('maker.php') . '?' . http_build_query(['id' => $id, 'page' => $makerPage + 1])) ?>">次へ</a>
    <?php endif; ?>
  </nav>
<?php else: ?>
  <?php pcf_render_empty('このメーカーの商品はありません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">ジャンルアクセスランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php $tabUrl = public_url('maker.php') . '?id=' . rawurlencode((string)$id) . '&rank_period=' . rawurlencode((string)$tabKey) . '#access-ranking'; ?>
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
            <th style="width:auto; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">ジャンル名</th>
            <th style="width:120px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">アクセス数</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessRankingRows as $index => $rankingRow): ?>
            <tr>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)($index + 1)) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:left;">
                <?php
                $rankingGenreUrl = public_url('genre.php') . '?id=' . rawurlencode((string)($rankingRow['id'] ?? ''));
                ?>
                <a href="<?= e($rankingGenreUrl) ?>"><?= e((string)($rankingRow['name'] ?? '')) ?></a>
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

<?php pcf_render_sample_movie_modal(); ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
