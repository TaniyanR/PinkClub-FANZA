<?php
declare(strict_types=1);

$moods = [
    ['label' => '可愛い作品', 'query' => '美少女 アイドル 制服 萌え 清楚'],
    ['label' => 'イチャイチャ系', 'query' => '恋人 ラブラブ 同棲 カップル イチャイチャ'],
    ['label' => '激しい作品', 'query' => 'ハード 激しい 絶頂 痙攣'],
    ['label' => 'ストーリー重視', 'query' => 'ドラマ ストーリー 長編 映画'],
];
?>
<nav aria-label="気分から作品を探す" style="display:flex;align-items:center;gap:8px 12px;flex-wrap:wrap;margin:0 0 14px;padding:8px 10px;border-bottom:1px solid #ddd;font-size:13px;line-height:1.5;">
  <strong style="font-size:13px;white-space:nowrap;">気分で探す：</strong>
  <?php foreach ($moods as $index => $mood): ?>
    <?php $href = public_url('search.php') . '?' . http_build_query(['q' => (string)$mood['query']]); ?>
    <?php if ($index > 0): ?><span aria-hidden="true" style="color:#aaa;">|</span><?php endif; ?>
    <a href="<?= e($href) ?>" style="color:#333;text-decoration:underline;text-underline-offset:2px;white-space:nowrap;"><?= e((string)$mood['label']) ?></a>
  <?php endforeach; ?>
</nav>
