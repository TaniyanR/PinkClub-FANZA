<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php';
$title='トップ';
$latest=db()->query('SELECT id,title,image_small,price_min_text FROM items ORDER BY updated_at DESC LIMIT 12')->fetchAll();
require __DIR__ . '/partials/header.php'; ?>
<h2>最新商品</h2><div class="grid"><?php foreach($latest as $item):?><div class="card"><a href="<?=e(app_url('public/item.php?id='.$item['id']))?>"><?=e($item['title'])?></a><br><?php if($item['image_small']):?><img class="thumb" src="<?=e($item['image_small'])?>"><?php else:?>画像なし<?php endif;?><br><?=e($item['price_min_text']??'')?></div><?php endforeach;?></div>
<?php require __DIR__ . '/partials/footer.php'; ?>
