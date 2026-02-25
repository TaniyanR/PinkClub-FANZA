<?php
require_once __DIR__ . '/_bootstrap.php';
$page=max(1,(int)($_GET['page']??1));$kw=trim((string)($_GET['q']??''));$where='';$bind=[];
if($kw!==''){ $where='WHERE name LIKE :q OR ruby LIKE :q'; $bind[':q']="%{$kw}%"; }
$c=db()->prepare("SELECT COUNT(*) FROM actresses {$where}");$c->execute($bind);$p=paginate((int)$c->fetchColumn(),$page,30);
$s=db()->prepare("SELECT a.*,COUNT(ia.item_id) item_count FROM actresses a LEFT JOIN item_actresses ia ON ia.actress_id=a.id {$where} GROUP BY a.id ORDER BY a.name LIMIT :lim OFFSET :off");
foreach($bind as $k=>$v)$s->bindValue($k,$v);$s->bindValue(':lim',$p['per_page'],PDO::PARAM_INT);$s->bindValue(':off',$p['offset'],PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll();
include __DIR__.'/partials/header.php'; ?>
<div class="card"><h1>女優一覧</h1><form><input name="q" value="<?= e($kw) ?>" placeholder="名前・読み"><button>検索</button></form></div>
<div class="grid"><?php foreach($rows as $r): ?><div class="card item-card"><?php if($r['image_small']): ?><img src="<?= e($r['image_small']) ?>"><?php endif; ?><a href="/public/actress.php?actress_id=<?= e((string)$r['actress_id']) ?>"><?= e($r['name']) ?></a><div>件数:<?= e((string)$r['item_count']) ?></div></div><?php endforeach; ?></div>
<div class="pagination card"><?php for($i=1;$i<=$p['pages'];$i++): ?><a href="?<?= http_build_query(['q'=>$kw,'page'=>$i]) ?>"><?= $i ?></a><?php endfor; ?></div>
<?php include __DIR__.'/partials/footer.php'; ?>
