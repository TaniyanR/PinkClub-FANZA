<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$rows = [];
try {
    $rows = fetch_makers(500, 0, 'name');
} catch (Throwable) {
    $rows = [];
}
$title = 'メーカー一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('メーカー一覧'); ?>

<?php if ($rows !== []): ?>
  <section class="taxonomy-grid pcf-grid"> 
    <?php foreach ($rows as $r): ?>
      <?php pcf_render_taxonomy_card((string)($r['name'] ?? ''), public_url('maker.php?id=' . (int)($r['id'] ?? 0)), $r['item_count'] ?? null); ?>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('メーカー情報がありません。'); ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
