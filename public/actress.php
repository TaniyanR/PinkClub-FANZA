<?php
require_once __DIR__ . '/_bootstrap.php';
$id=(int)($_GET['actress_id']??0);
$s=db()->prepare('SELECT * FROM actresses WHERE actress_id=:id LIMIT 1');$s->execute([':id'=>$id]);$a=$s->fetch();if(!$a){http_response_code(404);exit('not found');}
$sort=(($_GET['sort']??'date')==='review')?'i.review_average DESC,i.review_count DESC':'i.item_date DESC';
$items=db()->prepare("SELECT i.* FROM items i JOIN item_actresses ia ON ia.item_id=i.id WHERE ia.actress_id=:aid ORDER BY {$sort} LIMIT 60");$items->execute([':aid'=>$a['id']]);$items=$items->fetchAll();
include __DIR__.'/partials/header.php'; ?>
<div class="card"><h1><?= e($a['name']) ?></h1><?php if($a['image_large']): ?><img style="max-width:250px" src="<?= e($a['image_large']) ?>"><?php endif; ?><p>身長 <?= e((string)$a['height']) ?> / B<?= e((string)$a['bust']) ?> W<?= e((string)$a['waist']) ?> H<?= e((string)$a['hip']) ?> / 誕生日 <?= e((string)$a['birthday']) ?></p></div>
<div class="card"><a class="btn" href="?actress_id=<?= $id ?>&sort=date">新着順</a> <a class="btn secondary" href="?actress_id=<?= $id ?>&sort=review">人気順</a></div>
<div class="grid"><?php foreach($items as $it): ?><div class="card item-card"><img src="<?= e($it['image_small']) ?>"><a href="/public/item.php?content_id=<?= urlencode($it['content_id']) ?>"><?= e($it['title']) ?></a></div><?php endforeach; ?></div>
<?php include __DIR__.'/partials/footer.php'; ?>
