<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$page = max(1, (int)get('page', 1));
$per = 24;
$total = 0;
$rows = [];
$displayRows = [];

if (db_table_exists('actresses')) {
    try {
        $total = (int)db()->query('SELECT COUNT(*) FROM actresses')->fetchColumn();
    } catch (Throwable) {
        $total = 0;
    }
}

$pg = paginate($total, $page, $per);

if (db_table_exists('actresses')) {
    try {
        $rows = fetch_actresses((int)$pg['perPage'], (int)$pg['offset'], 'name');
    } catch (Throwable) {
        $rows = [];
    }
}

foreach ($rows as $r) {
    if (!is_array($r)) {
        continue;
    }
    $name = trim((string)($r['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    if (str_contains($name, 'http://') || str_contains($name, 'https://') || str_contains($name, '.') || str_contains($name, '/')) {
        continue;
    }
    $displayRows[] = $r;
}

$title = '女優一覧';
require __DIR__ . '/partials/header.php';

$indexMap = [];
foreach ($displayRows as $r) {
    if (!is_array($r)) {
        continue;
    }
    $name = trim((string)($r['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
    $indexMap[$initial][] = $r;
}
krsort($indexMap);
$pickupRows = array_slice($displayRows, 0, 8);
?>
<?php pcf_render_hero('女優一覧', '気になる女優のプロフィールと出演作品へ。'); ?>

<?php if ($displayRows !== []): ?>
  <nav class="pcf-index-nav">
    <?php foreach (array_keys($indexMap) as $initial): ?>
      <a class="pcf-index-nav__item" href="#actress-initial-<?= e(rawurlencode($initial)) ?>"><?= e($initial) ?></a>
    <?php endforeach; ?>
  </nav>

  <section class="pcf-pickup-grid">
    <?php foreach ($pickupRows as $r): ?>
      <?php
      $name = trim((string)($r['name'] ?? '名前未設定'));
      $img = '';
      foreach (['image_large', 'image_small', 'image_url'] as $key) {
          $val = trim((string)($r[$key] ?? ''));
          if ($val !== '') { $img = $val; break; }
      }
      ?>
      <a class="pcf-pickup-card" href="<?= e(public_url('actress.php?id=' . (int)($r['id'] ?? 0))) ?>">
        <img src="<?= e($img !== '' ? $img : pcf_placeholder_data_uri('No Photo')) ?>" alt="<?= e($name) ?>" loading="lazy">
        <span><?= e($name) ?></span>
      </a>
    <?php endforeach; ?>
  </section>

  <?php foreach ($indexMap as $initial => $groupRows): ?>
    <section class="pcf-index-block" id="actress-initial-<?= e(rawurlencode($initial)) ?>">
      <h2 class="pcf-section-title"><?= e($initial) ?></h2>
      <div class="pcf-directory">
        <?php foreach ($groupRows as $r): ?>
          <a class="pcf-directory__item" href="<?= e(public_url('actress.php?id=' . (int)($r['id'] ?? 0))) ?>">
            <span><?= e((string)($r['name'] ?? '')) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <?php pcf_render_pagination($pg, public_url('actresses.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('女優データが見つかりませんでした。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
