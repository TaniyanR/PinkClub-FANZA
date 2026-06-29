<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function pcf_label_access_period_from(string $period): string
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

function pcf_fetch_label_access_ranking(string $periodFrom): array
{
    try {
        if (!analytics_ensure_tables()) {
            throw new RuntimeException('analytics tables are not available');
        }

        foreach ([
            "SELECT il.label_id AS id, il.label_name AS name, COUNT(ol.id) AS access_count FROM out_logs ol INNER JOIN items i ON i.affiliate_url = ol.target_url INNER JOIN item_labels il ON i.content_id = il.content_id WHERE ol.created_at >= :period_from AND TRIM(COALESCE(i.affiliate_url, '')) <> '' GROUP BY il.label_id, il.label_name ORDER BY access_count DESC, il.label_name ASC LIMIT 200",
            "SELECT COALESCE(NULLIF(il.dmm_id, ''), il.label_name) AS id, il.label_name AS name, COUNT(ol.id) AS access_count FROM out_logs ol INNER JOIN items i ON i.affiliate_url = ol.target_url INNER JOIN item_labels il ON i.id = il.item_id WHERE ol.created_at >= :period_from AND TRIM(COALESCE(i.affiliate_url, '')) <> '' GROUP BY COALESCE(NULLIF(il.dmm_id, ''), il.label_name), il.label_name ORDER BY access_count DESC, il.label_name ASC LIMIT 200",
        ] as $sql) {
            try {
                $stmt = db()->prepare($sql);
                $stmt->execute([':period_from' => $periodFrom]);
                return $stmt->fetchAll() ?: [];
            } catch (Throwable) {
            }
        }
    } catch (Throwable) {
    }

    return [];
}

$id = trim((string)get('id', ''));
$name = trim((string)get('name', ''));
$page = max(1, (int)get('page', 1));
$limit = 24;
$offset = ($page - 1) * $limit;
$label = fetch_label($id, $name);
if ($label === null) {
    http_response_code(404);
    exit('not found');
}

$labelName = trim((string)($label['name'] ?? ''));
if ($labelName === '') {
    http_response_code(404);
    exit('not found');
}

$rows = dedupe_items_by_key(fetch_items_by_label_name($labelName, $limit + 1, $offset));
[$list, $hasNext] = paginate_items($rows, $limit);

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
$accessRankingRows = pcf_fetch_label_access_ranking(pcf_label_access_period_from($accessRankingPeriod));

$title = $labelName;
$pageDescription = mb_strimwidth($labelName . 'レーベルの作品一覧。FANZAで販売中の最新作・人気作品を紹介。', 0, 150, '…', 'UTF-8');
$canonicalUrl = public_url('label.php') . '?' . http_build_query(['id' => (string)($label['id'] ?? $id), 'name' => $labelName]);
if ($page > 1) {
    $relPrev = public_url('label.php') . '?' . http_build_query(['id' => (string)($label['id'] ?? $id), 'name' => $labelName, 'page' => $page - 1]);
}
if ($hasNext) {
    $relNext = public_url('label.php') . '?' . http_build_query(['id' => (string)($label['id'] ?? $id), 'name' => $labelName, 'page' => $page + 1]);
}
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'レーベル一覧', 'url' => public_url('labels.php')],
    ['label' => $labelName],
]); ?>
<?php pcf_render_hero($labelName); ?>

<h2 class="pcf-section-title"><?= e($labelName) ?>一覧</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid pcf-label-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
  <nav class="pcf-pagination" aria-label="ページネーション">
    <?php if ($page > 1): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('label.php') . '?' . http_build_query(['id' => (string)($label['id'] ?? $id), 'name' => $labelName, 'page' => $page - 1])) ?>">前へ</a>
    <?php endif; ?>
    <span class="pcf-pagination__link is-current"><?= e((string)$page) ?></span>
    <?php if ($hasNext): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('label.php') . '?' . http_build_query(['id' => (string)($label['id'] ?? $id), 'name' => $labelName, 'page' => $page + 1])) ?>">次へ</a>
    <?php endif; ?>
  </nav>
<?php else: ?>
  <?php pcf_render_empty('このレーベルの商品はありません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">レーベルクリックランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php $tabUrl = public_url('label.php') . '?' . http_build_query(['id' => (string)($label['id'] ?? $id), 'name' => $labelName, 'rank_period' => (string)$tabKey]) . '#access-ranking'; ?>
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
            <th style="width:auto; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">レーベル名</th>
            <th style="width:120px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">クリック数</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessRankingRows as $index => $rankingRow): ?>
            <tr>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)($index + 1)) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:left;">
                <?php
                $rankingLabelUrl = public_url('label.php') . '?' . http_build_query(['id' => (string)($rankingRow['id'] ?? ''), 'name' => (string)($rankingRow['name'] ?? '')]);
                ?>
                <a href="<?= e($rankingLabelUrl) ?>"><?= e((string)($rankingRow['name'] ?? '')) ?></a>
              </td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)((int)($rankingRow['access_count'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php pcf_render_empty('レーベルクリックランキングのデータがありません。'); ?>
  <?php endif; ?>
</section>

<?php pcf_render_sample_movie_modal(); ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
