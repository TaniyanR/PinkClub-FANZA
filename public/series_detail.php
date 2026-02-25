<?php declare(strict_types=1); require_once __DIR__ . '/_bootstrap.php'; $id=(int)get('id',0);$s=db()->prepare('SELECT * FROM series_master WHERE id=?');$s->execute([$id]);$row=$s->fetch();if(!$row){http_response_code(404);exit('not found');}$title='シリーズ詳細'; require __DIR__.'/partials/header.php'; ?>
<h2><?=e($row['name'])?></h2><p>ruby: <?=e($row['ruby']??'')?></p>
<?php require __DIR__.'/partials/footer.php'; ?>
