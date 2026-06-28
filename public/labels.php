<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function pcf_labels_access_period_from(string $period): string
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

function pcf_fetch_labels_access_ranking(string $periodFrom): array
{
    try {
        if (!analytics_ensure_tables()) {
            throw new RuntimeException('analytics tables are not available');
        }

        $stmt = db()->prepare("SELECT path, COUNT(id) AS access_count FROM site_events WHERE event_type = 'pv' AND session_id_hash = :marker AND created_at >= :period_from AND path LIKE '%/label.php?%' GROUP BY path ORDER BY access_count DESC, path ASC LIMIT 400");
        $stmt->execute([':period_from' => $periodFrom, ':marker' => analytics_beacon_marker_hash()]);
        $pathRows = $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }

    $rankingRows = [];
    foreach ($pathRows as $pathRow) {
        if (!is_array($pathRow)) {
            continue;
        }
        $query = (string)(parse_url((string)($pathRow['path'] ?? ''), PHP_URL_QUERY) ?? '');
        if ($query === '') {
            continue;
        }
        $params = [];
        parse_str($query, $params);
        $rankingLabel = fetch_label((string)($params['id'] ?? ''), (string)($params['name'] ?? ''));
        if ($rankingLabel === null) {
            continue;
        }
        $rankingName = trim((string)($rankingLabel['name'] ?? ''));
        if ($rankingName === '' || pcf_is_noise_name($rankingName)) {
            continue;
        }
        $rankingKey = (string)($rankingLabel['id'] ?? '') . '|' . $rankingName;
        if (!isset($rankingRows[$rankingKey])) {
            $rankingRows[$rankingKey] = [
                'id' => (string)($rankingLabel['id'] ?? ''),
                'name' => $rankingName,
                'access_count' => 0,
            ];
        }
        $rankingRows[$rankingKey]['access_count'] += (int)($pathRow['access_count'] ?? 0);
    }

    usort($rankingRows, static function (array $a, array $b): int {
        $countCompare = ((int)($b['access_count'] ?? 0)) <=> ((int)($a['access_count'] ?? 0));
        if ($countCompare !== 0) {
            return $countCompare;
        }
        return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    return array_slice($rankingRows, 0, 200);
}

$labels = [];
$displayRows = [];
try {
    for ($labelOffset = 0; ; $labelOffset += 200) {
        $labelPageRows = fetch_labels(200, $labelOffset);
        if ($labelPageRows === []) {
            break;
        }
        $labels = array_merge($labels, $labelPageRows);
        if (count($labelPageRows) < 200) {
            break;
        }
    }
} catch (Throwable) {
    $labels = [];
}

foreach ($labels as $label) {
    if (!is_array($label)) {
        continue;
    }
    $name = trim((string)($label['name'] ?? ''));
    if ($name === '' || pcf_is_noise_name($name)) {
        continue;
    }
    $displayRows[] = $label;
}

$kanaOrder = ['あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ'];
$kanaGroups = [];
foreach ($kanaOrder as $kana) {
    $kanaGroups[$kana] = [];
}
$alphaGroups = [];

$resolveIndex = static function (array $row): array {
    $name = trim((string)($row['name'] ?? ''));
    $ruby = trim((string)($row['ruby'] ?? ''));
    $base = $ruby !== '' ? $ruby : $name;
    $ch = mb_substr($base, 0, 1);
    if ($ch === '') {
        return ['type' => 'none', 'key' => ''];
    }
    $h = mb_convert_kana($ch, 'c', 'UTF-8');
    if (preg_match('/^[ぁ-お]/u', $h)) { return ['type' => 'kana', 'key' => 'あ']; }
    if (preg_match('/^[か-ご]/u', $h)) { return ['type' => 'kana', 'key' => 'か']; }
    if (preg_match('/^[さ-ぞ]/u', $h)) { return ['type' => 'kana', 'key' => 'さ']; }
    if (preg_match('/^[た-ど]/u', $h)) { return ['type' => 'kana', 'key' => 'た']; }
    if (preg_match('/^[な-の]/u', $h)) { return ['type' => 'kana', 'key' => 'な']; }
    if (preg_match('/^[は-ぽ]/u', $h)) { return ['type' => 'kana', 'key' => 'は']; }
    if (preg_match('/^[ま-も]/u', $h)) { return ['type' => 'kana', 'key' => 'ま']; }
    if (preg_match('/^[や-よ]/u', $h)) { return ['type' => 'kana', 'key' => 'や']; }
    if (preg_match('/^[ら-ろ]/u', $h)) { return ['type' => 'kana', 'key' => 'ら']; }
    if (preg_match('/^[わ-ん]/u', $h)) { return ['type' => 'kana', 'key' => 'わ']; }
    if (preg_match('/^[A-Za-z]/', $ch)) { return ['type' => 'alpha', 'key' => strtoupper($ch)]; }
    return ['type' => 'none', 'key' => ''];
};

foreach ($displayRows as $label) {
    $idx = $resolveIndex($label);
    if ($idx['type'] === 'kana' && isset($kanaGroups[$idx['key']])) {
        $kanaGroups[$idx['key']][] = $label;
        continue;
    }
    if ($idx['type'] === 'alpha') {
        $alphaGroups[$idx['key']][] = $label;
    }
}

$sortByName = static function (array &$list): void {
    usort($list, static function (array $a, array $b): int {
        return strcmp(mb_strtolower((string)($a['name'] ?? ''), 'UTF-8'), mb_strtolower((string)($b['name'] ?? ''), 'UTF-8'));
    });
};
foreach ($kanaGroups as &$groupRows) {
    $sortByName($groupRows);
}
unset($groupRows);
ksort($alphaGroups);
foreach ($alphaGroups as &$groupRows) {
    $sortByName($groupRows);
}
unset($groupRows);

$pageTitle = 'レーベル一覧';
$pageDescription = 'レーベル一覧ページです。';
$canonicalUrl = canonical_url('/labels.php');

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
$accessRankingRows = pcf_fetch_labels_access_ranking(pcf_labels_access_period_from($accessRankingPeriod));

include __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('レーベル一覧'); ?>

<?php if ($displayRows !== []): ?>
  <div class="pcf-kana-directory">
    <?php foreach ($kanaGroups as $kana => $groupRows): ?>
      <?php if ($groupRows === []): continue; endif; ?>
      <section class="pcf-index-block">
        <h2 class="pcf-section-title"><?= e($kana) ?>行</h2>
        <div class="pcf-list-card__meta">
          <?php foreach ($groupRows as $i => $label): ?>
            <?php if ($i > 0): ?>　<?php endif; ?><a href="<?= e(public_url('label.php') . '?' . http_build_query(['id' => (string)($label['id'] ?? ''), 'name' => (string)($label['name'] ?? '')])) ?>"><?= e((string)($label['name'] ?? '')) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <?php if ($alphaGroups !== []): ?>
      <section class="pcf-index-block">
        <h2 class="pcf-section-title">A~Z</h2>
        <?php foreach ($alphaGroups as $letter => $groupRows): ?>
          <div class="pcf-list-card__meta">
            <strong><?= e($letter) ?></strong>
            <?php foreach ($groupRows as $i => $label): ?>
              <?php if ($i > 0): ?>　<?php endif; ?><a href="<?= e(public_url('label.php') . '?' . http_build_query(['id' => (string)($label['id'] ?? ''), 'name' => (string)($label['name'] ?? '')])) ?>"><?= e((string)($label['name'] ?? '')) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php pcf_render_empty('レーベル情報がありません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">レーベルアクセスランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php $tabUrl = public_url('labels.php') . '?' . http_build_query(['rank_period' => (string)$tabKey]) . '#access-ranking'; ?>
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
<?php include __DIR__ . '/partials/footer.php'; ?>
