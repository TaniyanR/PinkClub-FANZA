<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php';
$page=max(1,(int)get('page',1));$per=20;$total=(int)db()->query('SELECT COUNT(*) FROM actresses')->fetchColumn();$pg=paginate($total,$page,$per);
$s=db()->prepare('SELECT * FROM actresses ORDER BY name LIMIT :l OFFSET :o');$s->bindValue(':l',$pg['perPage'],PDO::PARAM_INT);$s->bindValue(':o',$pg['offset'],PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll();
$title='女優一覧'; require __DIR__ . '/partials/header.php'; ?>
<h2>女優一覧</h2><ul><?php foreach($rows as $r):?><li><a href="<?=e(app_url('public/actress.php?id='.$r['id']))?>"><?=e($r['name'])?></a></li><?php endforeach;?></ul>
<?php require __DIR__ . '/partials/footer.php'; ?>
