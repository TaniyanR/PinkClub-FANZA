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

$title = (string)($row['name'] ?? '女優詳細');
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '女優一覧', 'url' => public_url('actresses.php')],
    ['label' => (string)($row['name'] ?? '女優詳細')],
]); ?>

<section class="pcf-profile">
  <img src="<?= e(trim((string)($row['image_url'] ?? '')) !== '' ? (string)$row['image_url'] : pcf_placeholder_data_uri('No Photo')) ?>" alt="<?= e((string)($row['name'] ?? '')) ?>">
  <div>
    <h1 class="pcf-hero__title"><?= e((string)($row['name'] ?? '')) ?></h1>
    <?php if (!empty($row['birthday'])): ?><p>誕生日: <?= e(format_date((string)$row['birthday'])) ?></p><?php endif; ?>
    <?php if (!empty($row['prefectures'])): ?><p>出身: <?= e((string)$row['prefectures']) ?></p><?php endif; ?>
    <p class="pcf-list-card__meta">関連作品: <?= e((string)count($list)) ?>件</p>
  </div>
</section>

<h2 class="pcf-section-title">関連作品</h2>
<?php if ($list !== []): ?>
  <section class="pcf-related-grid">
    <?php foreach ($list as $item): pcf_render_item_card(is_array($item) ? $item : []); endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('関連作品はまだありません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
