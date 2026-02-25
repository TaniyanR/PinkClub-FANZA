<?php
require_once __DIR__ . '/_bootstrap.php';
$contentId = (string)($_GET['content_id'] ?? '');
$stmt = db()->prepare('SELECT * FROM items WHERE content_id=:cid LIMIT 1'); $stmt->execute([':cid'=>$contentId]); $item = $stmt->fetch();
if (!$item) { http_response_code(404); exit('商品が見つかりません。'); }

$actresses = db()->prepare('SELECT a.* FROM item_actresses ia JOIN actresses a ON a.id=ia.actress_id WHERE ia.item_id=:id ORDER BY ia.sort_order'); $actresses->execute([':id'=>$item['id']]); $actresses = $actresses->fetchAll();
$genres = db()->prepare('SELECT g.* FROM item_genres ig JOIN genres g ON g.id=ig.genre_id WHERE ig.item_id=:id ORDER BY ig.sort_order'); $genres->execute([':id'=>$item['id']]); $genres = $genres->fetchAll();
$makers = db()->prepare('SELECT m.* FROM item_makers im JOIN makers m ON m.id=im.maker_id WHERE im.item_id=:id ORDER BY im.sort_order'); $makers->execute([':id'=>$item['id']]); $makers = $makers->fetchAll();
$series = db()->prepare('SELECT s.* FROM item_series isr JOIN series_master s ON s.id=isr.series_id WHERE isr.item_id=:id ORDER BY isr.sort_order'); $series->execute([':id'=>$item['id']]); $series = $series->fetchAll();
$authors = db()->prepare('SELECT a.* FROM item_authors iau JOIN authors a ON a.id=iau.author_id WHERE iau.item_id=:id ORDER BY iau.sort_order'); $authors->execute([':id'=>$item['id']]); $authors = $authors->fetchAll();
$campaigns = db()->prepare('SELECT * FROM item_campaigns WHERE item_id=:id ORDER BY sort_order'); $campaigns->execute([':id'=>$item['id']]); $campaigns = $campaigns->fetchAll();
$related = db()->prepare('SELECT * FROM items WHERE id<>:id AND (content_id IN (SELECT i2.content_id FROM items i2 JOIN item_makers im2 ON i2.id=im2.item_id JOIN item_makers im3 ON im3.maker_id=im2.maker_id WHERE im3.item_id=:id LIMIT 10)) ORDER BY item_date DESC LIMIT 8'); $related->execute([':id'=>$item['id']]); $related = $related->fetchAll();
$sampleS = json_decode((string)$item['sample_image_s_json'], true) ?: [];
include __DIR__ . '/partials/header.php';
?>
<div class="card"><h1><?= e($item['title']) ?></h1><img style="max-width:280px" src="<?= e($item['image_large'] ?: $item['image_small']) ?>" alt=""><p>価格: <?= e($item['price_text']) ?> / 発売日: <?= e((string)$item['item_date']) ?> / レビュー: ★<?= e((string)$item['review_average']) ?> (<?= e((string)$item['review_count']) ?>)</p>
<p><a class="btn" href="<?= e($item['affiliate_url']) ?>" target="_blank" rel="noopener">FANZAで見る</a></p>
<?php if ($item['sample_movie_720']): ?><p>サンプル動画: <a href="<?= e($item['sample_movie_720']) ?>" target="_blank">720p</a></p><?php endif; ?>
</div>
<div class="card"><h2>サンプル画像</h2><div class="grid"><?php foreach($sampleS as $u): ?><img src="<?= e(is_array($u)?($u['src'] ?? ''):$u) ?>" style="width:100%;height:160px;object-fit:cover"><?php endforeach; ?></div></div>
<div class="card"><h2>関連情報</h2><p>女優: <?php foreach($actresses as $a): ?><a href="/public/actress.php?actress_id=<?= e((string)$a['actress_id']) ?>"><?= e($a['name']) ?></a> <?php endforeach; ?></p><p>ジャンル: <?php foreach($genres as $g): ?><a href="/public/genre.php?genre_id=<?= e((string)$g['genre_id']) ?>"><?= e($g['name']) ?></a> <?php endforeach; ?></p><p>メーカー: <?php foreach($makers as $m): ?><a href="/public/maker.php?maker_id=<?= e((string)$m['maker_id']) ?>"><?= e($m['name']) ?></a> <?php endforeach; ?></p><p>シリーズ: <?php foreach($series as $s): ?><a href="/public/series_detail.php?series_id=<?= e((string)$s['series_id']) ?>"><?= e($s['name']) ?></a> <?php endforeach; ?></p><p>作者: <?php foreach($authors as $a): ?><a href="/public/author.php?author_id=<?= e((string)$a['author_id']) ?>"><?= e($a['name']) ?></a> <?php endforeach; ?></p></div>
<div class="card"><h2>キャンペーン</h2><?php foreach($campaigns as $c): $active = (!$c['date_begin'] || strtotime($c['date_begin'])<=time()) && (!$c['date_end'] || strtotime($c['date_end'])>=time()); ?><div class="alert <?= $active?'success':'' ?>"><?= e($c['title']) ?> (<?= e((string)$c['date_begin']) ?> - <?= e((string)$c['date_end']) ?>)</div><?php endforeach; ?></div>
<div class="card"><h2>関連商品</h2><div class="grid"><?php foreach($related as $r): ?><div class="item-card"><img src="<?= e($r['image_small']) ?>"><a href="/public/item.php?content_id=<?= urlencode($r['content_id']) ?>"><?= e($r['title']) ?></a></div><?php endforeach; ?></div></div>
<?php include __DIR__ . '/partials/footer.php'; ?>
