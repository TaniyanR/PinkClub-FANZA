<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

$title = '今の気分から探す';
$pageDescription = '今の気分に近いテーマを選んでFANZA作品を探せます。';
$canonicalUrl = public_url('mood.php');

$moods = [
    [
        'label' => '可愛い作品',
        'description' => '美少女・アイドル・制服など、可愛い雰囲気の作品を探します。',
        'query' => '美少女 アイドル 制服 萌え 清楚',
    ],
    [
        'label' => 'イチャイチャ系',
        'description' => '恋人・ラブラブ・同棲など、距離の近い雰囲気の作品を探します。',
        'query' => '恋人 ラブラブ 同棲 カップル イチャイチャ',
    ],
    [
        'label' => '激しい作品',
        'description' => '刺激の強いジャンルを中心に探します。',
        'query' => 'ハード 激しい 絶頂 痙攣',
    ],
    [
        'label' => 'ストーリー重視',
        'description' => 'ドラマ・ストーリー・長編など、物語を楽しめる作品を探します。',
        'query' => 'ドラマ ストーリー 長編 映画',
    ],
    [
        'label' => '人妻・熟女',
        'description' => '人妻・熟女系の作品を探します。',
        'query' => '人妻 熟女 奥様',
    ],
    [
        'label' => '新作から探す',
        'description' => '新しい作品を商品一覧から確認します。',
        'url' => public_url('items.php'),
    ],
    [
        'label' => '女優から探す',
        'description' => 'お気に入りの女優を選んで作品を探します。',
        'url' => public_url('actresses.php'),
    ],
];

require __DIR__ . '/partials/header.php';
?>

<?php pcf_render_hero('今の気分から探す', '気になるテーマを選ぶだけで、近い作品を探せます。'); ?>

<section aria-label="気分から作品を探す" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:18px 0 28px;">
  <?php foreach ($moods as $mood): ?>
    <?php
      $href = isset($mood['url'])
          ? (string)$mood['url']
          : public_url('search.php') . '?' . http_build_query(['q' => (string)$mood['query']]);
    ?>
    <a href="<?= e($href) ?>" style="display:block;padding:18px;border:1px solid #d6d6d6;border-radius:8px;background:#fff;color:#111;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,.06);">
      <strong style="display:block;margin-bottom:8px;font-size:18px;"><?= e((string)$mood['label']) ?></strong>
      <span style="display:block;line-height:1.7;color:#555;font-size:14px;"><?= e((string)$mood['description']) ?></span>
    </a>
  <?php endforeach; ?>
</section>

<p style="margin:0 0 24px;color:#666;font-size:13px;line-height:1.7;">このページは試作版です。作品の分類は、登録済みの商品名・ジャンルなどに含まれるキーワードを使っています。</p>

<?php require __DIR__ . '/partials/footer.php'; ?>
