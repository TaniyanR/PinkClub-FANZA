<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/favorites.php';
require_once __DIR__ . '/partials/public_ui.php';

$title = 'My Page';
$favorites = favorites_all();
$typeLabels = favorite_type_labels();
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => 'My Page'],
]); ?>
<?php pcf_render_hero('My Page', 'お気に入り一覧'); ?>

<?php foreach ($typeLabels as $type => $label): ?>
  <section class="block" style="margin-top:16px;">
    <h2 class="section-title"><?= e($label) ?>のお気に入り</h2>
    <?php $items = array_reverse($favorites[$type] ?? [], true); ?>
    <?php if ($items !== []): ?>
      <ul>
        <?php foreach ($items as $favorite): ?>
          <?php $favoriteId = (int)($favorite['id'] ?? 0); ?>
          <li>
            <a href="<?= e((string)($favorite['url'] ?? '')) ?>"><?= e((string)($favorite['title'] ?? '')) ?></a>
            <?php favorite_render_button((string)$type, $favoriteId, (string)($favorite['title'] ?? ''), (string)($favorite['url'] ?? '')); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?php pcf_render_empty($label . 'のお気に入りはまだありません。'); ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
