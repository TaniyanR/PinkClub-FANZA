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

if (
    trim((string)($row['image_large'] ?? '')) === '' &&
    trim((string)($row['image_small'] ?? '')) === '' &&
    trim((string)($row['image_url'] ?? '')) === ''
) {
    try {
        $client = dmm_client_for_type('actresses');
        $response = $client->searchActresses(['keyword' => $actressDisplayName, 'hits' => 20, 'offset' => 1]);
        $apiRows = DmmNormalizer::toList($response['result']['actress'] ?? []);
        foreach ($apiRows as $apiRow) {
            if (!is_array($apiRow)) {
                continue;
            }
            $apiId = trim((string)($apiRow['id'] ?? ''));
            $apiName = trim((string)($apiRow['name'] ?? ''));
            if ($apiId !== $dmmId && $apiName !== $actressDisplayName) {
                continue;
            }
            $row['ruby'] = $apiRow['ruby'] ?? ($row['ruby'] ?? null);
            $row['birthday'] = $apiRow['birthday'] ?? ($row['birthday'] ?? null);
            $row['prefectures'] = $apiRow['prefectures'] ?? ($row['prefectures'] ?? null);
            $row['image_url'] = $apiRow['imageURL']['large'] ?? ($apiRow['image_url'] ?? ($row['image_url'] ?? null));
            $row['image_small'] = $apiRow['imageURL']['small'] ?? ($apiRow['image_small'] ?? ($row['image_small'] ?? null));
            $row['image_large'] = $apiRow['imageURL']['large'] ?? ($apiRow['image_large'] ?? ($row['image_large'] ?? null));
            try {
                upsert_actress([
                    'dmm_id' => $dmmId,
                    'name' => $actressDisplayName,
                    'ruby' => $row['ruby'] ?? null,
                    'birthday' => $row['birthday'] ?? null,
                    'prefectures' => $row['prefectures'] ?? null,
                    'image_url' => $row['image_url'] ?? null,
                    'image_small' => $row['image_small'] ?? null,
                    'image_large' => $row['image_large'] ?? null,
                ]);
            } catch (Throwable) {
            }
            break;
        }
    } catch (Throwable) {
    }
}

try {
    $list = dedupe_items_by_key(fetch_items_by_actress((int)$row['id'], 100, 0));
} catch (Throwable) {
    $list = [];
}

$profileImage = trim((string)($row['image_large'] ?? ''));
if ($profileImage === '') {
    $profileImage = trim((string)($row['image_small'] ?? ''));
}
if ($profileImage === '') {
    $profileImage = trim((string)($row['image_url'] ?? ''));
}
if ($profileImage === '') {
    $profileImage = pcf_placeholder_data_uri('No Photo');
}

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
      <div><dt>ID</dt><dd><?= e($dmmId) ?></dd></div>
      <div><dt>よみ</dt><dd><?= e(trim((string)($row['ruby'] ?? '')) !== '' ? (string)$row['ruby'] : '未登録') ?></dd></div>
      <div><dt>誕生日</dt><dd><?= e(!empty($row['birthday']) ? format_date((string)$row['birthday']) : '未登録') ?></dd></div>
      <div><dt>出身</dt><dd><?= e(trim((string)($row['prefectures'] ?? '')) !== '' ? (string)$row['prefectures'] : '未登録') ?></dd></div>
      <div><dt>作品数</dt><dd><?= e((string)count($list)) ?>件</dd></div>
    </dl>
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
