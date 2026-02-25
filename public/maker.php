<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php';
$id=(int)get('id',0);$s=db()->prepare('SELECT * FROM makers WHERE id=?');$s->execute([$id]);$row=$s->fetch();if(!$row){http_response_code(404);exit('not found');}
$title='メーカー詳細'; require __DIR__.'/partials/header.php'; ?>
<h2><?=e($row['name'])?></h2><p>ruby: <?=e($row['ruby']??'')?></p>
<p>※ItemListからmaker関係を保持する場合は拡張可能。</p>
<?php require __DIR__.'/partials/footer.php'; ?>
