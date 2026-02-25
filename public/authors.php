<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php'; $rows=db()->query('SELECT * FROM authors ORDER BY name LIMIT 500')->fetchAll(); $title='作者一覧'; require __DIR__.'/partials/header.php'; ?>
<h2>作者一覧</h2><ul><?php foreach($rows as $r):?><li><a href="<?=e(app_url('public/author.php?id='.$r['id']))?>"><?=e($r['name'])?></a></li><?php endforeach;?></ul>
<?php require __DIR__.'/partials/footer.php'; ?>
