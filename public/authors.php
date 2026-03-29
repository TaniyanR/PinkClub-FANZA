<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

$rows = [];
backfill_master_from_relation('authors', 'item_authors', 'author_name');
if (db_table_exists('authors')) {
    if (db_table_exists('item_authors')) {
        try {
            $rows = db()->query('SELECT a.*, COUNT(ia.item_id) AS item_count FROM authors a LEFT JOIN item_authors ia ON ia.dmm_id = a.dmm_id GROUP BY a.id ORDER BY a.name LIMIT 500')->fetchAll() ?: [];
        } catch (Throwable) {
            try {
                $rows = db()->query('SELECT a.*, COUNT(ia.item_id) AS item_count FROM authors a LEFT JOIN item_authors ia ON ia.author_name = a.name GROUP BY a.id ORDER BY a.name LIMIT 500')->fetchAll() ?: [];
            } catch (Throwable) {
                $rows = db()->query('SELECT * FROM authors ORDER BY name LIMIT 500')->fetchAll() ?: [];
            }
        }
    } else {
        $rows = db()->query('SELECT * FROM authors ORDER BY name LIMIT 500')->fetchAll() ?: [];
    }
}

$title = '作者一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('作者一覧'); ?>

<?php if ($rows !== []): ?>
  <section class="pcf-directory">
    <?php foreach ($rows as $r): ?>
      <?php
      $name = trim((string)($r['name'] ?? ''));
      if ($name === '' || pcf_is_noise_name($name)) {
          continue;
      }
      ?>
      <a class="pcf-directory__item" href="<?= e(public_url('author.php?id=' . (int)($r['id'] ?? 0))) ?>">
        <span><?= e($name) ?></span>
        <?php if (!empty($r['item_count'])): ?><small><?= e((string)$r['item_count']) ?>件</small><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <?php pcf_render_empty('作者情報がありません。'); ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
