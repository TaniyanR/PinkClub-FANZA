<?php
declare(strict_types=1);

$moods = [
    ['label' => '可愛い作品', 'query' => '美少女 アイドル 制服 萌え 清楚'],
    ['label' => 'イチャイチャ系', 'query' => '恋人 ラブラブ 同棲 カップル イチャイチャ'],
    ['label' => '激しい作品', 'query' => 'ハード 激しい 絶頂 痙攣'],
    ['label' => 'ストーリー重視', 'query' => 'ドラマ ストーリー 長編 映画'],
];
?>
<section aria-label="気分から作品を探す" style="margin:14px auto 18px;padding:16px;max-width:1100px;border:1px solid #d6d6d6;border-radius:8px;background:#fff;box-sizing:border-box;">
  <h2 style="margin:0 0 6px;font-size:20px;">今日はどんな気分ですか？</h2>
  <p style="margin:0 0 14px;color:#666;font-size:14px;line-height:1.6;">気になる雰囲気を選ぶだけで、近い作品を探せます。</p>
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
    <?php foreach ($moods as $mood): ?>
      <?php $href = public_url('search.php') . '?' . http_build_query(['q' => (string)$mood['query']]); ?>
      <a href="<?= e($href) ?>" style="display:flex;align-items:center;justify-content:center;min-height:46px;padding:10px 12px;border:1px solid #222;border-radius:6px;background:#fff;color:#111;text-align:center;text-decoration:none;font-weight:700;box-sizing:border-box;"><?= e((string)$mood['label']) ?></a>
    <?php endforeach; ?>
  </div>
</section>
