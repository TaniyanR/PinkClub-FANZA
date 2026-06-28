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

function pcf_log_label_access(string $labelId, string $labelName): void
{
    $labelName = trim($labelName);
    if ($labelName === '' || !analytics_ensure_tables()) {
        return;
    }

    $path = '/label.php?' . http_build_query(['id' => $labelId, 'name' => $labelName]);
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $referrer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $hash = analytics_visitor_hash($ua);
    db()->prepare("INSERT INTO site_events(event_type,path,referrer,ua_hash,ip_hash,session_id_hash,created_at) VALUES('pv',:path,:referrer,:ua,:ip,:marker,NOW())")->execute([
        ':path' => mb_substr($path, 0, 255),
        ':referrer' => $referrer !== '' ? mb_substr($referrer, 0, 500) : null,
        ':ua' => $ua !== '' ? hash('sha256', $ua) : null,
        ':ip' => $hash,
        ':marker' => analytics_beacon_marker_hash(),
    ]);
}

function pcf_fetch_label_access_ranking(string $periodFrom): array
{
    try {
        if (!analytics_ensure_tables()) {
            throw new RuntimeException('analytics tables are not available');
        }

        $stmt = db()->prepare("SELECT path, COUNT(id) AS access_count FROM site_events WHERE event_type = 'pv' AND created_at >= :period_from AND path LIKE '/label.php?%' GROUP BY path ORDER BY access_count DESC LIMIT 2000");
        $stmt->execute([':period_from' => $periodFrom]);
        $paths = $stmt->fetchAll() ?: [];
        $ranking = [];
        foreach ($paths as $pathRow) {
            $query = (string)(parse_url((string)($pathRow['path'] ?? ''), PHP_URL_QUERY) ?? '');
            if ($query === '') {
                continue;
            }
            $params = [];
            parse_str($query, $params);
            $labelId = trim((string)($params['id'] ?? ''));
            $labelName = trim((string)($params['name'] ?? ''));
            if ($labelName === '') {
                continue;
            }
            $rankingKey = $labelId !== '' ? $labelId : $labelName;
            if (!isset($ranking[$rankingKey])) {
                $ranking[$rankingKey] = ['id' => $labelId, 'name' => $labelName, 'access_count' => 0];
            }
            $ranking[$rankingKey]['access_count'] += (int)($pathRow['access_count'] ?? 0);
        }
        usort($ranking, static function (array $a, array $b): int {
            $countCompare = ((int)($b['access_count'] ?? 0)) <=> ((int)($a['access_count'] ?? 0));
            if ($countCompare !== 0) {
                return $countCompare;
            }
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return array_slice($ranking, 0, 200);
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

try {
    pcf_log_label_access((string)($label['id'] ?? $id), $labelName);
    $skipAnalyticsBeacon = true;
} catch (Throwable $e) {
    error_log('label page view logging failed: ' . $e->getMessage());
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
  <h2 class="section-title">レーベルアクセスランキング</h2>
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
            <th style="width:120px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">アクセス数</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessRankingRows as $accessIndex => $accessRow): ?>
            <?php $labelUrl = public_url('label.php') . '?' . http_build_query(['id' => (string)($accessRow['id'] ?? ''), 'name' => (string)($accessRow['name'] ?? '')]); ?>
            <tr>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #eee; font-weight:700;"><?= e((string)($accessIndex + 1)) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><a href="<?= e($labelUrl) ?>"><?= e((string)($accessRow['name'] ?? '')) ?></a></td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #eee;"><?= e((string)((int)($accessRow['access_count'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php pcf_render_empty('レーベルアクセスランキングのデータがありません。'); ?>
  <?php endif; ?>
</section>

<?php pcf_render_sample_movie_modal(); ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
