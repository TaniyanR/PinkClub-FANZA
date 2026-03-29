<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

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

$name = trim((string)($row['name'] ?? ''));
$dmmId = trim((string)($row['dmm_id'] ?? ''));
if ($name === '' || pcf_is_noise_name($name) || str_starts_with($dmmId, 'name:') || !ctype_digit($dmmId)) {
    http_response_code(404);
    exit('not found');
}

try {
    $list = fetch_items_by_actress((int)$row['id'], 100, 0);
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

$title = $name;
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '女優一覧', 'url' => public_url('actresses.php')],
    ['label' => $name],
]); ?>

<section class="pcf-profile pcf-profile--plain">
  <img src="<?= e($profileImage) ?>" alt="<?= e($name) ?>">
  <div class="pcf-profile__body">
    <h1 class="pcf-hero__title"><?= e($name) ?></h1>
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
