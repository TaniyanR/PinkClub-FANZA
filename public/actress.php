<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$id = (int)get('id', 0);
$row = null;
$list = [];
try {
    $row = fetch_actress($id);
    if ($row !== null) {
        $list = fetch_items_by_actress((int)$row['id'], 100, 0);
    }
} catch (Throwable) {
    $row = null;
    $list = [];
}
if ($row === null) {
    http_response_code(404);
    exit('not found');
}

$actressName = trim((string)($row['name'] ?? ''));
$actressDmmId = trim((string)($row['dmm_id'] ?? ''));
if ($actressName === '' || pcf_is_noise_name($actressName) || str_starts_with($actressDmmId, 'name:')) {
    http_response_code(404);
    exit('not found');
}

$profileImage = trim((string)($row['image_large'] ?? ''));
if ($profileImage === '') {
    $profileImage = trim((string)($row['image_small'] ?? ''));
}
if ($profileImage === '') {
    $profileImage = trim((string)($row['image_url'] ?? ''));
}
if ($profileImage === '') {
    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }
        $candidate = trim((string)($item['image_large'] ?? ''));
        if ($candidate === '') {
            $candidate = trim((string)($item['image_small'] ?? ''));
        }
        if ($candidate === '') {
            $candidate = trim((string)($item['image_list'] ?? ''));
        }
        if ($candidate !== '') {
            $profileImage = $candidate;
            break;
        }
    }
}
if ($profileImage === '') {
    $profileImage = pcf_placeholder_data_uri('No Photo');
}

$title = (string)($row['name'] ?? '女優詳細');
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '女優一覧', 'url' => public_url('actresses.php')],
    ['label' => (string)($row['name'] ?? '女優詳細')],
]); ?>

<section class="pcf-profile">
  <img src="<?= e($profileImage) ?>" alt="<?= e((string)($row['name'] ?? '')) ?>">
  <div class="pcf-profile__body">
    <h1 class="pcf-hero__title"><?= e((string)($row['name'] ?? '')) ?></h1>
    <dl class="pcf-detail-list">
      <div><dt>名前</dt><dd><?= e((string)($row['name'] ?? '')) ?></dd></div>
      <?php if (!empty($row['ruby'])): ?><div><dt>よみ</dt><dd><?= e((string)$row['ruby']) ?></dd></div><?php endif; ?>
      <?php if (!empty($row['birthday'])): ?><div><dt>誕生日</dt><dd><?= e(format_date((string)$row['birthday'])) ?></dd></div><?php endif; ?>
      <?php if (!empty($row['prefectures'])): ?><div><dt>出身</dt><dd><?= e((string)$row['prefectures']) ?></dd></div><?php endif; ?>
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
