<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php';
$id=(int)get('id',0);$s=db()->prepare('SELECT * FROM actresses WHERE id=?');$s->execute([$id]);$row=$s->fetch();if(!$row){http_response_code(404);exit('not found');}
$items=db()->prepare('SELECT i.id,i.title FROM items i JOIN item_actresses ia ON ia.item_id=i.id WHERE ia.dmm_id=? OR ia.actress_name=? LIMIT 50');$items->execute([$row['dmm_id'],$row['name']]);$list=$items->fetchAll();
$title='女優詳細'; require __DIR__ . '/partials/header.php'; ?>
<h2><?=e($row['name'])?></h2><p>誕生日: <?=e($row['birthday']??'')?></p><p>出身: <?=e($row['prefectures']??'')?></p>
<?php if($row['image_url']):?><img src="<?=e($row['image_url'])?>" class="thumb"><?php endif; ?>
<h3>関連商品</h3><ul><?php foreach($list as $i):?><li><a href="<?=e(app_url('public/item.php?id='.$i['id']))?>"><?=e($i['title'])?></a></li><?php endforeach;?></ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
