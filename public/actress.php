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
    $client = dmm_client_for_type('actresses');
    $response = $client->searchActresses(['actress_id' => $dmmId, 'hits' => 1, 'offset' => 1]);
    $apiRows = DmmNormalizer::toList($response['result']['actress'] ?? []);

    if ($apiRows === []) {
        $response = $client->searchActresses(['keyword' => $actressDisplayName, 'hits' => 20, 'offset' => 1]);
        $apiRows = DmmNormalizer::toList($response['result']['actress'] ?? []);
    }

    foreach ($apiRows as $apiRow) {
        if (!is_array($apiRow)) {
            continue;
        }
        $apiId = trim((string)($apiRow['id'] ?? ''));
        $apiName = trim((string)($apiRow['name'] ?? ''));
        if ($apiId !== $dmmId && $apiName !== $actressDisplayName) {
            continue;
        }

        $profile['name'] = $apiName !== '' ? $apiName : $profile['name'];
        $profile['ruby'] = (string)($apiRow['ruby'] ?? $profile['ruby']);
        $profile['birthday'] = (string)($apiRow['birthday'] ?? $profile['birthday']);
        $profile['prefectures'] = (string)($apiRow['prefectures'] ?? $profile['prefectures']);
        $profile['image_url'] = (string)($apiRow['imageURL']['large'] ?? $apiRow['image_url'] ?? $profile['image_url']);
        $profile['image_small'] = (string)($apiRow['imageURL']['small'] ?? $apiRow['image_small'] ?? $profile['image_small']);
        $profile['image_large'] = (string)($apiRow['imageURL']['large'] ?? $apiRow['image_large'] ?? $profile['image_large']);
        $profile['bust'] = trim((string)($apiRow['bust'] ?? ''));
        $profile['cup'] = trim((string)($apiRow['cup'] ?? ''));
        $profile['waist'] = trim((string)($apiRow['waist'] ?? ''));
        $profile['hip'] = trim((string)($apiRow['hip'] ?? ''));
        $profile['height'] = trim((string)($apiRow['height'] ?? ''));
        $profile['blood_type'] = trim((string)($apiRow['blood_type'] ?? ''));
        $profile['hobby'] = trim((string)($apiRow['hobby'] ?? ''));
        $profile['listurl_digital'] = trim((string)($apiRow['listURL']['digital'] ?? ''));
        $profile['listurl_monthly'] = trim((string)($apiRow['listURL']['monthly'] ?? ''));
        $profile['listurl_mono'] = trim((string)($apiRow['listURL']['mono'] ?? ''));

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
        } catch (Throwable) {
        }
        break;
    }
} catch (Throwable) {
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

<section class="pcf-profile pcf-profile--plain">
  <img src="<?= e($profileImage) ?>" alt="<?= e($actressDisplayName) ?>">
  <div class="pcf-profile__body">
    <h1 class="pcf-hero__title"><?= e($actressDisplayName) ?></h1>
    <dl class="pcf-detail-list">
      <div><dt>女優ID</dt><dd><?= e($profile['dmm_id']) ?></dd></div>
      <div><dt>よみ</dt><dd><?= e(actress_profile_value($profile, 'ruby')) ?></dd></div>
      <div><dt>誕生日</dt><dd><?= e(!empty($profile['birthday']) ? format_date((string)$profile['birthday']) : '未登録') ?></dd></div>
      <div><dt>出身地</dt><dd><?= e(actress_profile_value($profile, 'prefectures')) ?></dd></div>
      <div><dt>バスト</dt><dd><?= e(actress_profile_value($profile, 'bust')) ?></dd></div>
      <div><dt>カップ</dt><dd><?= e(actress_profile_value($profile, 'cup')) ?></dd></div>
      <div><dt>ウエスト</dt><dd><?= e(actress_profile_value($profile, 'waist')) ?></dd></div>
      <div><dt>ヒップ</dt><dd><?= e(actress_profile_value($profile, 'hip')) ?></dd></div>
      <div><dt>身長</dt><dd><?= e(actress_profile_value($profile, 'height')) ?></dd></div>
      <div><dt>血液型</dt><dd><?= e(actress_profile_value($profile, 'blood_type')) ?></dd></div>
      <div><dt>趣味</dt><dd><?= e(actress_profile_value($profile, 'hobby')) ?></dd></div>
      <div><dt>作品数</dt><dd><?= e((string)count($list)) ?>件</dd></div>
    </dl>
    <div class="pcf-inline-links">
      <?php if ($profile['listurl_digital'] !== ''): ?><a href="<?= e($profile['listurl_digital']) ?>" target="_blank" rel="noopener noreferrer">動画一覧</a><?php endif; ?>
      <?php if ($profile['listurl_monthly'] !== ''): ?><a href="<?= e($profile['listurl_monthly']) ?>" target="_blank" rel="noopener noreferrer">月額動画一覧</a><?php endif; ?>
      <?php if ($profile['listurl_mono'] !== ''): ?><a href="<?= e($profile['listurl_mono']) ?>" target="_blank" rel="noopener noreferrer">DVD一覧</a><?php endif; ?>
    </div>
  </div>
</section>

<h2 class="pcf-section-title">作品一覧</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('関連作品はまだありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
