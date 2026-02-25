<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php';
$id=(int)get('id',0);$s=db()->prepare('SELECT * FROM genres WHERE id=?');$s->execute([$id]);$row=$s->fetch();if(!$row){http_response_code(404);exit('not found');}
$items=db()->prepare('SELECT i.id,i.title,i.image_small FROM items i JOIN item_genres ig ON ig.item_id=i.id WHERE ig.dmm_id=? OR ig.genre_name=? LIMIT 100');$items->execute([$row['dmm_id'],$row['name']]);$list=$items->fetchAll();
$title='ジャンル詳細'; require __DIR__.'/partials/header.php'; ?>
<h2><?=e($row['name'])?></h2><h3>商品一覧</h3><ul><?php foreach($list as $i):?><li><a href="<?=e(app_url('public/item.php?id='.$i['id']))?>"><?=e($i['title'])?></a></li><?php endforeach;?></ul>
<?php require __DIR__.'/partials/footer.php'; ?>
