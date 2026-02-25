<?php
require_once __DIR__ . '/_bootstrap.php';
$newItems = db()->query('SELECT * FROM items ORDER BY item_date DESC LIMIT 12')->fetchAll();
$popularItems = db()->query('SELECT * FROM items ORDER BY review_average DESC, review_count DESC LIMIT 12')->fetchAll();
$newActresses = db()->query('SELECT * FROM actresses ORDER BY updated_at DESC LIMIT 8')->fetchAll();
$makers = db()->query('SELECT m.*,COUNT(im.item_id) cnt FROM makers m LEFT JOIN item_makers im ON im.maker_id=m.id GROUP BY m.id ORDER BY cnt DESC LIMIT 15')->fetchAll();
$genres = db()->query('SELECT g.*,COUNT(ig.item_id) cnt FROM genres g LEFT JOIN item_genres ig ON ig.genre_id=g.id GROUP BY g.id ORDER BY cnt DESC LIMIT 15')->fetchAll();
include __DIR__ . '/partials/header.php';
?>
<div class="card"><h1>トップページ</h1></div>
<div class="card"><h2>新着商品</h2><div class="grid"><?php foreach($newItems as $it): ?><div class="item-card card"><img src="<?= e($it['image_small'] ?: $it['image_large']) ?>" alt=""><a href="/public/item.php?content_id=<?= urlencode($it['content_id']) ?>"><?= e($it['title']) ?></a></div><?php endforeach; ?></div></div>
<div class="card"><h2>人気商品</h2><div class="grid"><?php foreach($popularItems as $it): ?><div class="item-card card"><img src="<?= e($it['image_small'] ?: $it['image_large']) ?>" alt=""><a href="/public/item.php?content_id=<?= urlencode($it['content_id']) ?>"><?= e($it['title']) ?></a></div><?php endforeach; ?></div></div>
<div class="grid"><div class="card"><h2>女優新着</h2><?php foreach($newActresses as $a): ?><div><a href="/public/actress.php?actress_id=<?= e((string)$a['actress_id']) ?>"><?= e($a['name']) ?></a></div><?php endforeach; ?></div>
<div class="card"><h2>メーカー</h2><?php foreach($makers as $m): ?><div><a href="/public/maker.php?maker_id=<?= e((string)$m['maker_id']) ?>"><?= e($m['name']) ?></a> (<?= e((string)$m['cnt']) ?>)</div><?php endforeach; ?></div>
<div class="card"><h2>ジャンル</h2><?php foreach($genres as $g): ?><div><a href="/public/genre.php?genre_id=<?= e((string)$g['genre_id']) ?>"><?= e($g['name']) ?></a> (<?= e((string)$g['cnt']) ?>)</div><?php endforeach; ?></div></div>
<?php include __DIR__ . '/partials/footer.php'; ?>
