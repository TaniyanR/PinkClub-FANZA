<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php';
$page=max(1,(int)get('page',1)); $per=app_config()['pagination']['per_page'];
$total=(int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn(); $pg=paginate($total,$page,$per);
$stmt=db()->prepare('SELECT * FROM items ORDER BY release_date DESC,id DESC LIMIT :l OFFSET :o');
$stmt->bindValue(':l',$pg['perPage'],PDO::PARAM_INT);$stmt->bindValue(':o',$pg['offset'],PDO::PARAM_INT);$stmt->execute();$rows=$stmt->fetchAll();
$title='商品一覧'; require __DIR__ . '/partials/header.php'; ?>
<h2>商品一覧</h2><table><tr><th>画像</th><th>タイトル</th><th>発売日</th><th>価格</th></tr><?php foreach($rows as $r):?><tr><td><?php if($r['image_small']):?><img class="thumb" src="<?=e($r['image_small'])?>"><?php else:?>-<?php endif;?></td><td><a href="<?=e(app_url('public/item.php?id='.$r['id']))?>"><?=e($r['title'])?></a></td><td><?=e($r['release_date']??'')?></td><td><?=e($r['price_min_text']??'')?></td></tr><?php endforeach;?></table>
<p>Page <?=e((string)$pg['page'])?> / <?=e((string)$pg['pages'])?></p>
<?php require __DIR__ . '/partials/footer.php'; ?>
