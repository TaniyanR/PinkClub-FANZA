<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$page = max(1, (int)get('page', 1));
$per = 24;
$total = 0;
$rows = [];

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

$title = '女優一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('女優一覧', '気になる女優のプロフィールと出演作品へ。'); ?>

<?php if ($rows !== []): ?>
  <section class="pcf-grid">
    <?php foreach ($rows as $r): ?>
      <?php
      $name = trim((string)($r['name'] ?? '名前未設定'));
      $birthday = trim((string)($r['birthday'] ?? ''));
      $pref = trim((string)($r['prefectures'] ?? ''));
      $img = '';
      foreach (['image_large', 'image_small', 'image_url'] as $key) {
          $val = trim((string)($r[$key] ?? ''));
          if ($val !== '') { $img = $val; break; }
      }
      ?>
      <article class="pcf-card pcf-list-card">
        <img class="pcf-item-card__thumb" src="<?= e($img !== '' ? $img : pcf_placeholder_data_uri('No Photo')) ?>" alt="<?= e($name) ?>" loading="lazy">
        <h3 class="pcf-list-card__title"><?= e($name) ?></h3>
        <div class="pcf-list-card__meta">
          <?php if ($birthday !== ''): ?><div>誕生日: <?= e(format_date($birthday)) ?></div><?php endif; ?>
          <?php if ($pref !== ''): ?><div>出身: <?= e($pref) ?></div><?php endif; ?>
        </div>
        <p><a class="pcf-btn" href="<?= e(public_url('actress.php?id=' . (int)($r['id'] ?? 0))) ?>">詳細を見る</a></p>
      </article>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('actresses.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('女優データが見つかりませんでした。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
