<?php
require_once __DIR__ . '/_bootstrap.php';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where = []; $bind = [];
if (($kw = trim((string)($_GET['keyword'] ?? ''))) !== '') { $where[] = 'i.title LIKE :kw'; $bind[':kw'] = "%{$kw}%"; }
$join = '';
foreach (['actress'=>'item_actresses ia','genre'=>'item_genres ig','maker'=>'item_makers im','series'=>'item_series isr','author'=>'item_authors iau'] as $k=>$tbl) {
    $id = (int)($_GET[$k.'_id'] ?? 0);
    if ($id>0) { $join .= " JOIN {$tbl} ON {$tbl}.item_id=i.id "; $col = $k.'_id'; $where[] = "{$tbl}.{$col}=:{$col}"; $bind[":{$col}"] = $id; }
}
$order = (($_GET['sort'] ?? 'date') === 'review') ? 'i.review_average DESC, i.review_count DESC' : 'i.item_date DESC';
$sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$totalStmt = db()->prepare("SELECT COUNT(DISTINCT i.id) FROM items i {$join} {$sqlWhere}"); $totalStmt->execute($bind); $total = (int)$totalStmt->fetchColumn();
$p = paginate($total,$page,$perPage);
$listStmt = db()->prepare("SELECT DISTINCT i.* FROM items i {$join} {$sqlWhere} ORDER BY {$order} LIMIT :lim OFFSET :off");
foreach($bind as $k=>$v) $listStmt->bindValue($k,$v);
$listStmt->bindValue(':lim',$p['per_page'],PDO::PARAM_INT); $listStmt->bindValue(':off',$p['offset'],PDO::PARAM_INT); $listStmt->execute();
$items = $listStmt->fetchAll();
include __DIR__ . '/partials/header.php';
?>
<div class="card"><h1>商品一覧</h1>
<form method="get" class="grid"><input name="keyword" placeholder="keyword" value="<?= e($_GET['keyword'] ?? '') ?>"><input name="actress_id" placeholder="actress_id" value="<?= e($_GET['actress_id'] ?? '') ?>"><input name="genre_id" placeholder="genre_id" value="<?= e($_GET['genre_id'] ?? '') ?>"><input name="maker_id" placeholder="maker_id" value="<?= e($_GET['maker_id'] ?? '') ?>"><input name="series_id" placeholder="series_id" value="<?= e($_GET['series_id'] ?? '') ?>"><input name="author_id" placeholder="author_id" value="<?= e($_GET['author_id'] ?? '') ?>"><select name="sort"><option value="date">新着</option><option value="review" <?= (($_GET['sort'] ?? '')==='review')?'selected':'' ?>>人気</option></select><button>検索</button></form>
</div>
<div class="grid"><?php foreach($items as $it): ?><div class="card item-card"><img src="<?= e($it['image_small'] ?: $it['image_large']) ?>" alt=""><div><a href="/public/item.php?content_id=<?= urlencode($it['content_id']) ?>"><?= e($it['title']) ?></a></div><small>価格:<?= e($it['price_text']) ?> / 発売:<?= e((string)$it['item_date']) ?> / ★<?= e((string)$it['review_average']) ?></small></div><?php endforeach; ?></div>
<div class="pagination card"><?php for($i=1;$i<=$p['pages'];$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?></div>
<?php include __DIR__ . '/partials/footer.php'; ?>
