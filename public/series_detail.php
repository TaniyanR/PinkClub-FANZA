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

function pcf_fetch_series_access_ranking(string $periodFrom): array
{
    try {
        if (!analytics_ensure_tables()) {
            throw new RuntimeException('analytics tables are not available');
        }

        $stmt = db()->prepare("SELECT sm.id, sm.dmm_id, sm.name, COUNT(se.id) AS access_count FROM site_events se INNER JOIN series_master sm ON se.path = CONCAT('/series_detail.php?id=', sm.id) WHERE se.event_type = 'pv' AND se.created_at >= :period_from GROUP BY sm.id, sm.dmm_id, sm.name ORDER BY access_count DESC, sm.id DESC LIMIT 200");
        $stmt->execute([':period_from' => $periodFrom]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

$id = (int)get('id', 0);
$page = max(1, (int)get('page', 1));
$per = 24;
$series = null;
$seriesItems = [];
$total = 0;
$pg = paginate(0, $page, $per);
try {
    $series = fetch_series_one($id);
    if ($series !== null) {
        $total = function_exists('count_items_by_series') ? count_items_by_series((int)$series['id']) : 0;
        $pg = paginate($total, $page, $per);
        $seriesItems = dedupe_items_by_key(fetch_items_by_series((int)$series['id'], (int)$pg['perPage'], (int)$pg['offset']));
    }
} catch (Throwable) {
    $series = null;
    $seriesItems = [];
    $total = 0;
    $pg = paginate(0, $page, $per);
}
if ($series === null) {
    http_response_code(404);
    exit('not found');
}

$seriesName = (string)($series['name'] ?? 'シリーズ詳細');
try {
    analytics_log_series_page_view((int)$series['id']);
} catch (Throwable $e) {
    error_log('series page view logging failed: ' . $e->getMessage());
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
$accessRankingRows = pcf_fetch_series_access_ranking(pcf_series_access_period_from($accessRankingPeriod));

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
<?php if ($seriesItems !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($seriesItems as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('series_detail.php'), ['id' => (int)$series['id']]); ?>
<?php else: ?>
  <?php pcf_render_empty('このシリーズの作品はまだありません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">シリーズアクセスランキング</h2>
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
            <th style="width:auto; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">シリーズ名</th>
            <th style="width:120px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">アクセス数</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessRankingRows as $accessIndex => $accessRow): ?>
            <?php $seriesUrl = public_url('series_detail.php') . '?id=' . rawurlencode((string)($accessRow['id'] ?? '')); ?>
            <tr>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #eee; font-weight:700;"><?= e((string)($accessIndex + 1)) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><a href="<?= e($seriesUrl) ?>"><?= e((string)($accessRow['name'] ?? '')) ?></a></td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #eee;"><?= e((string)((int)($accessRow['access_count'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php pcf_render_empty('シリーズアクセスランキングのデータがありません。'); ?>
  <?php endif; ?>
</section>

<?php pcf_render_sample_movie_modal(); ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
