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

$apiSyncStatus = ['attempted' => false, 'success' => false, 'message' => ''];

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
    'listurl_digital' => '',
    'listurl_monthly' => '',
    'listurl_mono' => '',
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
        $profile['listurl_digital'] = trim((string)($bestApiRow['listURL']['digital'] ?? ''));
        $profile['listurl_monthly'] = trim((string)($bestApiRow['listURL']['monthly'] ?? ''));
        $profile['listurl_mono'] = trim((string)($bestApiRow['listURL']['mono'] ?? ''));

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
    $list = dedupe_items_by_key(fetch_items_by_actress((int)$row['id'], 100, 0));
} catch (Throwable) {
    $list = [];
}

$profileImage = actress_profile_image($profile);
$actressDisplayName = $profile['name'];

unset($title, $pageTitle);
$title = $actressDisplayName;
$pageTitle = $actressDisplayName;
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '女優一覧', 'url' => public_url('actresses.php')],
    ['label' => $actressDisplayName],
]); ?>

<?php if ($apiSyncStatus['attempted']): ?>
  <div class="admin-notice <?= $apiSyncStatus['success'] ? 'admin-notice--success' : 'admin-notice--error' ?>"><p><?= e((string)$apiSyncStatus['message']) ?></p></div>
<?php endif; ?>

<section class="pcf-profile pcf-profile--plain" style="display:grid;grid-template-columns:minmax(220px,320px) 1fr;gap:20px;align-items:start;">
  <img src="<?= e($profileImage) ?>" alt="<?= e($actressDisplayName) ?>" style="width:100%;max-width:320px;aspect-ratio:1/1;object-fit:cover;border-radius:6px;">
  <div class="pcf-profile__body">
    <h1 class="pcf-hero__title" style="margin-top:0;"><?= e($actressDisplayName) ?></h1>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
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

<?php if ($list !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('関連作品はまだありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
