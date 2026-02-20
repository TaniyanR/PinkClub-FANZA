<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';
$page=max(1,(int)($_GET['page']??1));$limit=24;$offset=($page-1)*$limit;[$genres,$hasNext]=paginate_items(fetch_genres($limit+1,$offset),$limit);
$pageStyles = ['/assets/css/genres.css'];
$pageTitle='ジャンル一覧';$pageDescription='ジャンル一覧です。';$canonicalUrl=canonical_url('/genres.php',['page'=>$page>1?$page:null]);
include __DIR__ . '/partials/header.php'; include __DIR__ . '/partials/nav_search.php'; ?>
<div class="layout"><?php include __DIR__ . '/partials/sidebar.php'; ?><main class="main-content"><section class="block"><div class="section-head"><h1 class="section-title">ジャンル一覧</h1></div><div class="taxonomy-grid"><?php foreach($genres as $genre): ?><a class="taxonomy-card" href="/genre.php?id=<?php echo urlencode((string)$genre['id']); ?>"><div class="taxonomy-card__media">#<?php echo e((string)$genre['id']); ?></div><div class="taxonomy-card__name"><?php echo e((string)$genre['name']); ?></div></a><?php endforeach; ?></div></section><nav class="pagination"><?php if($page>1):?><a class="page-btn" href="/genres.php?page=<?php echo e((string)($page-1)); ?>">前へ</a><?php else:?><span class="page-btn">前へ</span><?php endif; ?><span class="page-btn is-current"><?php echo e((string)$page); ?></span><?php if($hasNext):?><a class="page-btn" href="/genres.php?page=<?php echo e((string)($page+1)); ?>">次へ</a><?php else:?><span class="page-btn">次へ</span><?php endif; ?></nav></main></div><?php include __DIR__ . '/partials/footer.php'; ?>
