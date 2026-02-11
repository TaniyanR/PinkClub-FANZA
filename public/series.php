<?php
declare(strict_types=1);
require_once __DIR__ . '/partials/_helpers.php';
require_once __DIR__ . '/../lib/repository.php';
$page=max(1,(int)($_GET['page']??1));$limit=24;$offset=($page-1)*$limit;[$series,$hasNext]=paginate_items(fetch_series($limit+1,$offset),$limit);
$pageTitle='シリーズ一覧 | PinkClub-FANZA';$pageDescription='シリーズ一覧です。';$canonicalUrl=canonical_url('/series.php',['page'=>$page>1?$page:null]);
include __DIR__ . '/partials/header.php'; include __DIR__ . '/partials/nav_search.php'; ?>
<div class="layout"><?php include __DIR__ . '/partials/sidebar.php'; ?><main class="main-content"><section class="block"><div class="section-head"><h1 class="section-title">シリーズ一覧</h1></div><div class="taxonomy-grid"><?php foreach($series as $entry): ?><a class="taxonomy-card" href="/series_one.php?id=<?php echo urlencode((string)$entry['id']); ?>"><div class="taxonomy-card__media">#<?php echo e((string)$entry['id']); ?></div><div class="taxonomy-card__name"><?php echo e($entry['name']); ?></div></a><?php endforeach; ?></div></section><nav class="pagination"><?php if($page>1):?><a class="page-btn" href="/series.php?page=<?php echo e((string)($page-1)); ?>">前へ</a><?php else:?><span class="page-btn">前へ</span><?php endif; ?><span class="page-btn is-current"><?php echo e((string)$page); ?></span><?php if($hasNext):?><a class="page-btn" href="/series.php?page=<?php echo e((string)($page+1)); ?>">次へ</a><?php else:?><span class="page-btn">次へ</span><?php endif; ?></nav></main></div><?php include __DIR__ . '/partials/footer.php'; ?>
