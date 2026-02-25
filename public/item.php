<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php';
$id=(int)get('id',0); $stmt=db()->prepare('SELECT * FROM items WHERE id=?');$stmt->execute([$id]);$item=$stmt->fetch();
if(!$item){http_response_code(404); exit('not found');}
$rels=[]; foreach(['item_actresses'=>'actress_name','item_genres'=>'genre_name','item_labels'=>'label_name','item_campaigns'=>'campaign_name','item_directors'=>'director_name'] as $t=>$c){$s=db()->prepare("SELECT {$c} FROM {$t} WHERE item_id=?");$s->execute([$id]);$rels[$c]=$s->fetchAll(PDO::FETCH_COLUMN);} 
$title=$item['title']; require __DIR__ . '/partials/header.php'; ?>
<h2><?=e($item['title'])?></h2><?php if($item['image_large']):?><img src="<?=e($item['image_large'])?>" style="max-width:320px"><?php else:?><p>画像なし</p><?php endif;?>
<ul><li>価格: <?=e($item['price_min_text']??'')?></li><li>発売日: <?=e($item['release_date']??'')?></li><li>レビュー: <?=e((string)$item['review_average'])?> (<?=e((string)$item['review_count'])?>)</li></ul>
<?php foreach($rels as $name=>$vals):?><p><?=e($name)?>: <?=e(implode(', ',array_filter($vals)))?></p><?php endforeach; ?>
<?php if($item['affiliate_url']):?><p><a href="<?=e($item['affiliate_url'])?>" target="_blank">FANZAで見る</a></p><?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
