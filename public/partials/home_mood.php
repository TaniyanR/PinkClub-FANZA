<?php
declare(strict_types=1);

$moods = [
    ['label' => '可愛い作品', 'query' => '美少女 アイドル 制服 萌え 清楚'],
    ['label' => 'イチャイチャ系', 'query' => '恋人 ラブラブ 同棲 カップル イチャイチャ'],
    ['label' => '激しい作品', 'query' => 'ハード 激しい 絶頂 痙攣'],
    ['label' => 'ストーリー重視', 'query' => 'ドラマ ストーリー 長編 映画'],
];
?>
<section aria-label="気分から作品を探す" style="margin:0 0 16px;padding:12px 14px;border:1px solid #e0e0e0;border-radius:6px;background:#fff;box-sizing:border-box;">
  <strong style="display:block;margin:0 0 10px;font-size:14px;line-height:1.4;">【 気分で探す 】</strong>
  <div style="display:flex;align-items:center;justify-content:flex-start;gap:8px;flex-wrap:wrap;">
    <?php foreach ($moods as $mood): ?>
      <?php $href = public_url('search.php') . '?' . http_build_query(['q' => (string)$mood['query']]); ?>
      <a href="<?= e($href) ?>" style="display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:6px 12px;border:1px solid #777;border-radius:5px;background:#fff;color:#222;text-decoration:none;font-size:13px;font-weight:700;line-height:1.2;box-sizing:border-box;white-space:nowrap;"><?= e((string)$mood['label']) ?></a>
    <?php endforeach; ?>
  </div>
</section>