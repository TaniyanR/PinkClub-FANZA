<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function is_invalid_actress_name(string $name): bool
{
    if (pcf_is_noise_name($name)) {
        return true;
    }
    $v = mb_strtolower(trim($name), 'UTF-8');
    if ($v === '') {
        return true;
    }
    foreach (['相互リンク', '相互rss', 'お問い合わせ', 'privacy policy', 'プライバシー', 'サイトについて', '公式サイト', 'オフィシャルサイト'] as $ng) {
        if (str_contains($v, mb_strtolower($ng, 'UTF-8'))) {
            return true;
        }
    }
    return false;
}

function actress_profile_value(array $profile, string $key): string
{
    $value = trim((string)($profile[$key] ?? ''));
    return $value !== '' ? $value : '未登録';
}


function actress_api_row_score(array $apiRow): int
{
    $score = 0;
    foreach (['bust', 'cup', 'waist', 'hip', 'height', 'birthday', 'blood_type', 'hobby', 'prefectures', 'ruby'] as $key) {
        if (trim((string)($apiRow[$key] ?? '')) !== '') {
            $score++;
        }
    }

    if (trim((string)($apiRow['imageURL']['large'] ?? $apiRow['image_large'] ?? '')) !== '') {
        $score += 2;
    }
    if (trim((string)($apiRow['imageURL']['small'] ?? $apiRow['image_small'] ?? '')) !== '') {
        $score += 1;
    }

    return $score;
}

function actress_profile_image(array $profile): string
{
    foreach (['image_large', 'image_small', 'image_url'] as $key) {
        $value = trim((string)($profile[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return pcf_placeholder_data_uri('No Photo');
}

$id = (int)get('id', 0);
if ($id <= 0) {
    http_response_code(404);
    exit('not found');
}

$row = null;
$list = [];
try {
    $row = fetch_actress($id);
} catch (Throwable) {
    $row = null;
}

if (!is_array($row)) {
    http_response_code(404);
    exit('not found');
}

$actressDisplayName = trim((string)($row['name'] ?? ''));
$dmmId = trim((string)($row['dmm_id'] ?? ''));
if ($actressDisplayName === '' || is_invalid_actress_name($actressDisplayName) || str_starts_with($dmmId, 'name:') || !ctype_digit($dmmId)) {
    http_response_code(404);
    exit('not found');
}

try {
    analytics_log_actress_page_view((int)$row['id']);
} catch (Throwable $e) {
    error_log('actress page view logging failed: ' . $e->getMessage());
}

$apiSyncStatus = ['attempted' => false, 'success' => false, 'message' => ''];
$actressPage = max(1, (int)get('page', 1));
$limit = 24;
$offset = ($actressPage - 1) * $limit;
$hasNext = false;

$profile = [
    'dmm_id' => $dmmId,
    'name' => $actressDisplayName,
    'ruby' => (string)($row['ruby'] ?? ''),
    'birthday' => (string)($row['birthday'] ?? ''),
    'prefectures' => (string)($row['prefectures'] ?? ''),
    'image_url' => (string)($row['image_url'] ?? ''),
    'image_small' => (string)($row['image_small'] ?? ''),
    'image_large' => (string)($row['image_large'] ?? ''),
    'bust' => '',
    'cup' => '',
    'waist' => '',
    'hip' => '',
    'height' => '',
    'blood_type' => '',
    'hobby' => '',
];

try {
    $apiSyncStatus['attempted'] = true;
    $client = dmm_client_for_type('actresses');
    $response = $client->searchActresses(['actress_id' => $dmmId, 'hits' => 10, 'offset' => 1]);
    $apiRows = DmmNormalizer::toList($response['result']['actress'] ?? []);

    $keywordResponse = $client->searchActresses(['keyword' => $actressDisplayName, 'hits' => 20, 'offset' => 1]);
    $keywordRows = DmmNormalizer::toList($keywordResponse['result']['actress'] ?? []);
    if ($keywordRows !== []) {
        $apiRows = array_merge($apiRows, $keywordRows);
    }

    $bestApiRow = null;
    $bestScore = -1;
    foreach ($apiRows as $apiRow) {
        if (!is_array($apiRow)) {
            continue;
        }
        $apiId = trim((string)($apiRow['id'] ?? ''));
        $apiName = trim((string)($apiRow['name'] ?? ''));
        if ($apiId !== $dmmId && $apiName !== $actressDisplayName) {
            continue;
        }

        $score = actress_api_row_score($apiRow);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestApiRow = $apiRow;
        }
    }

    if (is_array($bestApiRow)) {
        $profile['name'] = trim((string)($bestApiRow['name'] ?? '')) !== '' ? (string)$bestApiRow['name'] : $profile['name'];
        $profile['ruby'] = (string)($bestApiRow['ruby'] ?? $profile['ruby']);
        $profile['birthday'] = (string)($bestApiRow['birthday'] ?? $profile['birthday']);
        $profile['prefectures'] = (string)($bestApiRow['prefectures'] ?? $profile['prefectures']);
        $profile['image_url'] = (string)($bestApiRow['imageURL']['large'] ?? $bestApiRow['image_url'] ?? $profile['image_url']);
        $profile['image_small'] = (string)($bestApiRow['imageURL']['small'] ?? $bestApiRow['image_small'] ?? $profile['image_small']);
        $profile['image_large'] = (string)($bestApiRow['imageURL']['large'] ?? $bestApiRow['image_large'] ?? $profile['image_large']);
        $profile['bust'] = trim((string)($bestApiRow['bust'] ?? ''));
        $profile['cup'] = trim((string)($bestApiRow['cup'] ?? ''));
        $profile['waist'] = trim((string)($bestApiRow['waist'] ?? ''));
        $profile['hip'] = trim((string)($bestApiRow['hip'] ?? ''));
        $profile['height'] = trim((string)($bestApiRow['height'] ?? ''));
        $profile['blood_type'] = trim((string)($bestApiRow['blood_type'] ?? ''));
        $profile['hobby'] = trim((string)($bestApiRow['hobby'] ?? ''));

        try {
            upsert_actress([
                'dmm_id' => $profile['dmm_id'],
                'name' => $profile['name'],
                'ruby' => $profile['ruby'],
                'birthday' => $profile['birthday'],
                'prefectures' => $profile['prefectures'],
                'image_url' => $profile['image_url'],
                'image_small' => $profile['image_small'],
                'image_large' => $profile['image_large'],
            ]);
        } catch (Throwable $e) {
            error_log('actress.php upsert_actress failed: ' . $e->getMessage());
        }
        $apiSyncStatus['success'] = true;
        $apiSyncStatus['message'] = '女優APIでプロフィールを最新化しました。';
    } else {
        $apiSyncStatus['message'] = '女優APIの一致データが見つからなかったため、保存済み情報を表示しています。';
    }
} catch (Throwable $e) {
    $apiSyncStatus['message'] = '女優APIの取得に失敗したため、保存済み情報を表示しています。';
    error_log('actress.php ActressSearch failed: ' . $e->getMessage());
}

try {
    [$list, $hasNext] = paginate_items(dedupe_items_by_key(fetch_items_by_actress((int)$row['id'], $limit + 1, $offset)), $limit);
} catch (Throwable) {
    $list = [];
    $hasNext = false;
}

$profileImage = actress_profile_image($profile);
$actressDisplayName = $profile['name'];

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

    if (!analytics_ensure_tables()) {
        throw new RuntimeException('analytics tables are not available');
    }

    $rankingStmt = db()->prepare("SELECT a.id, a.dmm_id, a.name, COUNT(se.id) AS access_count FROM site_events se INNER JOIN actresses a ON se.path = CONCAT('/actress.php?id=', a.id) WHERE se.event_type = 'pv' AND se.created_at >= :period_from GROUP BY a.id, a.dmm_id, a.name ORDER BY access_count DESC, a.id DESC LIMIT 200");
    $rankingStmt->execute([':period_from' => $periodFrom]);
    $accessRankingRows = $rankingStmt->fetchAll() ?: [];
    $accessRankingRows = array_values(array_filter($accessRankingRows, static function ($rankingRow): bool {
        if (!is_array($rankingRow)) {
            return false;
        }
        $rankingName = trim((string)($rankingRow['name'] ?? ''));
        $rankingDmmId = trim((string)($rankingRow['dmm_id'] ?? ''));
        return $rankingName !== '' && !is_invalid_actress_name($rankingName) && ctype_digit($rankingDmmId);
    }));
} catch (Throwable) {
    $accessRankingRows = [];
}

unset($title, $pageTitle);
$title = $actressDisplayName;
$pageTitle = $actressDisplayName;
$pageDescription = mb_strimwidth($actressDisplayName . 'の出演作品一覧。FANZAで人気の' . $actressDisplayName . '作品をチェック。', 0, 150, '…', 'UTF-8');
$canonicalUrl = public_url('actress.php') . '?id=' . rawurlencode((string)$id);
$ogImage = $profileImage;
if ($actressPage > 1) {
    $relPrev = public_url('actress.php') . '?' . http_build_query(['id' => $id, 'page' => $actressPage - 1]);
}
if ($hasNext) {
    $relNext = public_url('actress.php') . '?' . http_build_query(['id' => $id, 'page' => $actressPage + 1]);
}
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '女優一覧', 'url' => public_url('actresses.php')],
    ['label' => $actressDisplayName],
]); ?>

<section class="pcf-profile pcf-profile--plain pcf-actress-profile" style="display:grid;grid-template-columns:minmax(220px,320px) 1fr;gap:20px;align-items:start;">
  <img class="pcf-actress-profile__image" src="<?= e($profileImage) ?>" alt="<?= e($actressDisplayName) ?>" style="width:100%;max-width:320px;aspect-ratio:1/1;object-fit:cover;border-radius:6px;">
  <div class="pcf-profile__body pcf-actress-profile__body">
    <h1 class="pcf-hero__title" style="margin:0 0 16px;padding:0 0 6px 8px;border-left:8px solid #002bff;border-bottom:2px solid #002bff;"><?= e($actressDisplayName) ?></h1>
    <div class="pcf-actress-profile__details" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
      <dl class="pcf-detail-list" style="margin:0;">
        <div><dt>よみ</dt><dd><?= e(actress_profile_value($profile, 'ruby')) ?></dd></div>
        <div><dt>誕生日</dt><dd><?= e(!empty($profile['birthday']) ? format_date((string)$profile['birthday']) : '未登録') ?></dd></div>
        <div><dt>出身地</dt><dd><?= e(actress_profile_value($profile, 'prefectures')) ?></dd></div>
        <div><dt>趣味</dt><dd><?= e(actress_profile_value($profile, 'hobby')) ?></dd></div>
      </dl>
      <dl class="pcf-detail-list" style="margin:0;">
        <div><dt>バスト</dt><dd><?= e(actress_profile_value($profile, 'bust')) ?></dd></div>
        <div><dt>カップ</dt><dd><?= e(actress_profile_value($profile, 'cup')) ?></dd></div>
        <div><dt>ウエスト</dt><dd><?= e(actress_profile_value($profile, 'waist')) ?></dd></div>
        <div><dt>ヒップ</dt><dd><?= e(actress_profile_value($profile, 'hip')) ?></dd></div>
        <div><dt>身長</dt><dd><?= e(actress_profile_value($profile, 'height')) ?></dd></div>
        <div><dt>血液型</dt><dd><?= e(actress_profile_value($profile, 'blood_type')) ?></dd></div>
      </dl>
    </div>
  </div>
</section>

<h2 class="pcf-section-title" style="margin:15px 0 12px;padding-bottom:10px;border-bottom:2px solid #d7dbe3;"><?= e($actressDisplayName) ?>の作品</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid pcf-item-related-grid pcf-actress-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
  <nav class="pcf-pagination" aria-label="ページネーション">
    <?php if ($actressPage > 1): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('actress.php') . '?' . http_build_query(['id' => $id, 'page' => $actressPage - 1])) ?>">前へ</a>
    <?php endif; ?>
    <span class="pcf-pagination__link is-current"><?= e((string)$actressPage) ?></span>
    <?php if ($hasNext): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('actress.php') . '?' . http_build_query(['id' => $id, 'page' => $actressPage + 1])) ?>">次へ</a>
    <?php endif; ?>
  </nav>
<?php else: ?>
  <?php pcf_render_empty('関連作品はまだありません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">女優アクセスランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php $tabUrl = public_url('actress.php') . '?id=' . rawurlencode((string)$id) . '&rank_period=' . rawurlencode((string)$tabKey) . '#access-ranking'; ?>
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
            <th style="width:auto; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">女優名</th>
            <th style="width:120px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">アクセス数</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessRankingRows as $index => $rankingRow): ?>
            <tr>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)($index + 1)) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:left;">
                <?php
                $rankingActressUrl = public_url('actress.php') . '?id=' . rawurlencode((string)($rankingRow['id'] ?? ''));
                ?>
                <a href="<?= e($rankingActressUrl) ?>"><?= e((string)($rankingRow['name'] ?? '')) ?></a>
              </td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)((int)($rankingRow['access_count'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php pcf_render_empty('女優アクセスランキングのデータがありません。'); ?>
  <?php endif; ?>
</section>

<div id="sample-movie-modal" class="sample-movie-modal" aria-hidden="true">
  <div class="sample-movie-modal__overlay" data-movie-close="1"></div>
  <div class="sample-movie-modal__dialog" role="dialog" aria-modal="true" aria-label="サンプル動画プレイヤー">
    <button type="button" class="sample-movie-modal__close" data-movie-close="1" aria-label="閉じる">×</button>
    <div id="sample-movie-title" class="sample-movie-modal__title">サンプル動画</div>
    <div class="sample-movie-modal__frame-wrap">
      <iframe id="sample-movie-frame" class="sample-movie-modal__frame" src="about:blank" allow="autoplay; fullscreen" referrerpolicy="no-referrer"></iframe>
    </div>
  </div>
</div>
<script>
(() => {
  const modal = document.getElementById('sample-movie-modal');
  const frame = document.getElementById('sample-movie-frame');
  const titleNode = document.getElementById('sample-movie-title');
  if (!modal || !frame || !titleNode) return;

  const openMovie = (url, title) => {
    if (!url) return;
    const normalizedTitle = String(title || '').trim();
    titleNode.textContent = normalizedTitle !== '' ? normalizedTitle : 'サンプル動画';
    modal.style.setProperty('--movie-modal-width', '900px');
    frame.src = url;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  };

  const closeMovie = () => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    frame.src = 'about:blank';
    modal.style.removeProperty('--movie-modal-width');
    titleNode.textContent = 'サンプル動画';
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.sample-movie-trigger');
    if (trigger && !trigger.disabled) {
      event.preventDefault();
      const card = trigger.closest('.pcf-dm-card');
      const fallbackTitle = card ? (card.querySelector('.pcf-dm-card__title')?.textContent || '') : '';
      openMovie(trigger.dataset.movieUrl || '', trigger.dataset.movieTitle || fallbackTitle);
      return;
    }

    if (event.target.closest('[data-movie-close="1"]')) {
      event.preventDefault();
      closeMovie();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
      closeMovie();
    }
  });
})();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
