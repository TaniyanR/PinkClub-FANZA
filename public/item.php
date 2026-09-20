<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/public_rankings.php';
require_once __DIR__ . '/partials/public_ui.php';

$id = (int)get('id', 0);
$contentId = trim((string)get('content_id', ''));
$cid = trim((string)get('cid', ''));

$db = db();

$item = null;
if ($id > 0) {
    $stmt = $db->prepare('SELECT * FROM items WHERE id = :id AND ' . items_product_source_where() . ' LIMIT 1');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
}

if (!$item && $contentId !== '') {
    $stmt = $db->prepare('SELECT * FROM items WHERE content_id = :cid AND ' . items_product_source_where() . ' LIMIT 1');
    $stmt->execute([':cid' => $contentId]);
    $item = $stmt->fetch();
}

if (!$item && $cid !== '') {
    $stmt = $db->prepare('SELECT * FROM items WHERE (content_id = :cid OR dmm_id = :cid) AND ' . items_product_source_where() . ' LIMIT 1');
    $stmt->execute([':cid' => $cid]);
    $item = $stmt->fetch();
}

if (!$item) {
    http_response_code(404);
    $title = '商品が見つかりません';
    require __DIR__ . '/partials/header.php';
    echo '<p class="error-box">指定された商品は存在しないか、非公開です。</p>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$id = (int)$item['id'];
$itemId = $id;

try {
    pcf_log_access_event($db, 'item', (int)$item['id'], (string)$item['title'], 'pv');
} catch (Throwable $e) {
    error_log('item page view logging failed: ' . $e->getMessage());
}

$actressesStmt = $db->prepare('
    SELECT a.*
    FROM item_actresses ia
    INNER JOIN actresses a ON a.dmm_id = ia.dmm_id
    WHERE ia.item_id = :item_id
    ORDER BY a.name ASC
');
$actressesStmt->execute([':item_id' => $id]);
$actresses = $actressesStmt->fetchAll();

$genresStmt = $db->prepare('
    SELECT g.*
    FROM item_genres ig
    INNER JOIN genres g ON g.dmm_id = ig.dmm_id
    WHERE ig.item_id = :item_id
    ORDER BY g.name ASC
');
$genresStmt->execute([':item_id' => $id]);
$genres = $genresStmt->fetchAll();

$makersStmt = $db->prepare('
    SELECT m.*
    FROM item_makers im
    INNER JOIN makers m ON m.dmm_id = im.dmm_id
    WHERE im.item_id = :item_id
    ORDER BY m.name ASC
');
$makersStmt->execute([':item_id' => $id]);
$makers = $makersStmt->fetchAll();

$seriesStmt = $db->prepare('
    SELECT s.*
    FROM item_series ise
    INNER JOIN series_master s ON s.dmm_id = ise.dmm_id
    WHERE ise.item_id = :item_id
    ORDER BY s.name ASC
');
$seriesStmt->execute([':item_id' => $id]);
$series = $seriesStmt->fetchAll();

$labels = pcf_item_labels($db, $id);

$directAffiliateUrl = trim((string)($item['affiliate_url'] ?? ''));
$affiliateUrl = pcf_out_url((int)$item['id'], 'item_detail');

$sampleMovieUrl = '';
foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476', 'sample_movie_url', 'sample_movie_url_pc'] as $movieKey) {
    $candidate = trim((string)($item[$movieKey] ?? ''));
    if ($candidate !== '') {
        $sampleMovieUrl = $candidate;
        break;
    }
}
if ($sampleMovieUrl === '') {
    $fallbackMovie = item_sample_movie_url($item);
    $sampleMovieUrl = is_string($fallbackMovie) ? $fallbackMovie : '';
}

$sampleImages = [];
$sampleImagesCount = 0;
if (!empty($item['sample_images'])) {
    $decoded = json_decode((string)$item['sample_images'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $k => $v) {
            if (is_string($v) && $v !== '') {
                $sampleImages[] = $v;
            } elseif (is_array($v)) {
                $candidate = (string)($v['xlarge'] ?? $v['large'] ?? $v['medium'] ?? $v['small'] ?? $v['image'] ?? '');
                if ($candidate !== '') {
                    $sampleImages[] = $candidate;
                }
            }
        }
    }
}
if ($sampleImages === []) {
    $sampleImages = item_sample_image_urls($item);
}
$sampleImagesCount = count($sampleImages);

$relatedItems = [];
$relatedIds = [];

if ($actresses !== []) {
    $actressDmmIds = array_filter(array_map(static fn($a) => (string)($a['dmm_id'] ?? ''), $actresses));
    if ($actressDmmIds !== []) {
        $inClause = implode(',', array_fill(0, count($actressDmmIds), '?'));
        $relStmt = $db->prepare("
            SELECT DISTINCT i.*
            FROM item_actresses ia
            INNER JOIN items i ON i.id = ia.item_id
            WHERE ia.dmm_id IN ($inClause)
              AND i.id <> ?
              AND " . items_product_source_where('i') . "
            ORDER BY i.date_released DESC, i.id DESC
            LIMIT 12
        ");
        $params = array_values($actressDmmIds);
        $params[] = $id;
        $relStmt->execute($params);
        $relatedItems = $relStmt->fetchAll();
        $relatedIds = array_map(static fn($r) => (int)$r['id'], $relatedItems);
    }
}

if (count($relatedItems) < 6 && $genres !== []) {
    $genreDmmIds = array_filter(array_map(static fn($g) => (string)($g['dmm_id'] ?? ''), $genres));
    if ($genreDmmIds !== []) {
        $exclude = array_merge([$id], $relatedIds);
        $inGenres = implode(',', array_fill(0, count($genreDmmIds), '?'));
        $exClause = implode(',', array_fill(0, count($exclude), '?'));
        $limit = 12 - count($relatedItems);
        $relStmt = $db->prepare("
            SELECT DISTINCT i.*
            FROM item_genres ig
            INNER JOIN items i ON i.id = ig.item_id
            WHERE ig.dmm_id IN ($inGenres)
              AND i.id NOT IN ($exClause)
              AND " . items_product_source_where('i') . "
            ORDER BY i.date_released DESC, i.id DESC
            LIMIT $limit
        ");
        $params = array_merge(array_values($genreDmmIds), $exclude);
        $relStmt->execute($params);
        $genreItems = $relStmt->fetchAll();
        $relatedItems = array_merge($relatedItems, $genreItems);
    }
}

$relatedItems = pcf_normalize_items_for_public($relatedItems);

$title = (string)$item['title'];
$metaTitle = $title . ' - 作品詳細 | PinkClub';

$descBase = !empty($item['comment'])
    ? strip_tags((string)$item['comment'])
    : (!empty($item['description']) ? strip_tags((string)$item['description']) : $title);
$pageDescription = mb_strimwidth(trim(preg_replace('/\s+/u', ' ', $descBase)), 0, 150, '…', 'UTF-8');

$canonicalUrl = public_url('item.php') . '?id=' . rawurlencode((string)$id);

$packageImage = '';
if (function_exists('pcf_pick_detail_main_image')) {
    $packageImage = pcf_pick_detail_main_image($item);
} elseif (function_exists('pcf_item_image')) {
    $packageImage = pcf_item_image($item);
}
if ($packageImage === '') {
    $packageImage = trim((string)($item['image_large'] ?? $item['image_small'] ?? $item['image_url'] ?? ''));
}
$ogImage = $packageImage !== '' ? $packageImage : (!empty($item['image_url']) ? (string)$item['image_url'] : '');

$breadcrumbTitle = mb_strimwidth($title, 0, 24, '…', 'UTF-8');

$actressNames = [];
foreach ($actresses as $a) {
    $name = trim((string)($a['name'] ?? ''));
    if ($name !== '' && !is_invalid_actress_name($name)) {
        $actressNames[] = $name;
    }
}

$productJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $title,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
];
if ($ogImage !== '') {
    $productJsonLd['image'] = $ogImage;
}
if ($directAffiliateUrl !== '') {
    $productJsonLd['offers'] = [
        '@type' => 'Offer',
        'url' => $directAffiliateUrl,
        'priceCurrency' => 'JPY',
        'price' => !empty($item['price']) ? (string)$item['price'] : '0',
        'availability' => 'https://schema.org/InStock',
    ];
}
if ($actressNames !== []) {
    $productJsonLd['actor'] = array_map(static fn($name) => ['@type' => 'Person', 'name' => $name], $actressNames);
}
$jsonLd = (string)json_encode($productJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

$accessRankingPeriod = trim((string)get('rank_period', 'daily'));
$accessRankingTabs = [
    'daily' => ['label' => '本日'],
    'weekly' => ['label' => '週間'],
    'monthly' => ['label' => '月間'],
    'yearly' => ['label' => '年間'],
];
if (!isset($accessRankingTabs[$accessRankingPeriod])) {
    $accessRankingPeriod = 'daily';
}
$accessRankingRowsByPeriod = [];
foreach (array_keys($accessRankingTabs) as $tabPeriod) {
    $accessRankingRowsByPeriod[$tabPeriod] = pcf_public_weighted_ranking('items', (string)$tabPeriod);
}

require __DIR__ . '/partials/header.php';
?>

<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '商品一覧', 'url' => public_url('items.php')],
    ['label' => $breadcrumbTitle],
]); ?>

<article class="item-detail">
  <div class="item-detail__header">
    <h1 class="item-detail__title"><?= e($title) ?></h1>
  </div>

  <div class="item-detail__main">
    <div class="item-detail__media">
      <div class="item-detail__cover-wrap">
        <?php if ($packageImage !== ''): ?>
          <img class="item-detail__cover" src="<?= e($packageImage) ?>" alt="<?= e($title) ?>" loading="eager" decoding="async">
        <?php else: ?>
          <div class="item-card__no-image" style="height:320px;">NO IMAGE</div>
        <?php endif; ?>
      </div>

      <?php if ($sampleImages !== []): ?>
        <div class="item-detail__samples">
          <h2 class="item-detail__samples-heading">サンプル画像（全<?= count($sampleImages) ?>枚）</h2>
          <div class="sample-gallery" id="sample-gallery">
            <?php foreach ($sampleImages as $idx => $sUrl): ?>
              <div class="sample-gallery__item">
                <img
                  src="<?= e($sUrl) ?>"
                  alt="サンプル <?= $idx + 1 ?>"
                  loading="lazy"
                  decoding="async"
                  data-image-index="<?= $idx ?>"
                  data-full-src="<?= e($sUrl) ?>"
                >
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="item-detail__info">
      <div class="item-detail__action-primary">
        <a href="<?= e($affiliateUrl) ?>" target="_blank" rel="nofollow noopener noreferrer" class="btn btn--fanza btn--lg btn--block btn--pulse">
          FANZA公式サイトで見る
        </a>
      </div>

      <?php if ($sampleMovieUrl !== ''): ?>
        <div class="item-detail__action-secondary" style="margin-top:10px;">
          <button type="button" class="btn btn--secondary btn--block" data-sample-movie-url="<?= e($sampleMovieUrl) ?>" data-sample-movie-title="<?= e($title) ?>">
            サンプル動画を再生
          </button>
        </div>
      <?php endif; ?>

      <dl class="item-meta-list">
        <?php if (!empty($item['content_id'])): ?>
          <div class="item-meta-list__row">
            <dt>品番</dt>
            <dd><?= e((string)$item['content_id']) ?></dd>
          </div>
        <?php endif; ?>

        <?php if (!empty($item['date_released']) && $item['date_released'] !== '0000-00-00'): ?>
          <div class="item-meta-list__row">
            <dt>発売日</dt>
            <dd><?= e((string)$item['date_released']) ?></dd>
          </div>
        <?php endif; ?>

        <?php if ($actresses !== []): ?>
          <div class="item-meta-list__row">
            <dt>出演女優</dt>
            <dd class="item-meta-list__tags">
              <?php foreach ($actresses as $actress): ?>
                <?php
                  $aName = trim((string)($actress['name'] ?? ''));
                  if ($aName === '' || is_invalid_actress_name($aName)) {
                      continue;
                  }
                  $aUrl = public_url('actress.php') . '?id=' . rawurlencode((string)$actress['id']);
                ?>
                <a href="<?= e($aUrl) ?>" class="tag tag--actress"><?= e($aName) ?></a>
              <?php endforeach; ?>
            </dd>
          </div>
        <?php endif; ?>

        <?php if ($makers !== []): ?>
          <div class="item-meta-list__row">
            <dt>メーカー</dt>
            <dd>
              <?php foreach ($makers as $maker): ?>
                <a href="<?= e(public_url('maker.php') . '?id=' . rawurlencode((string)$maker['id'])) ?>" class="link-subtle">
                  <?= e((string)$maker['name']) ?>
                </a>
              <?php endforeach; ?>
            </dd>
          </div>
        <?php endif; ?>

        <?php if ($series !== []): ?>
          <div class="item-meta-list__row">
            <dt>シリーズ</dt>
            <dd>
              <?php foreach ($series as $s): ?>
                <a href="<?= e(public_url('series_detail.php') . '?id=' . rawurlencode((string)$s['id'])) ?>" class="link-subtle">
                  <?= e((string)$s['name']) ?>
                </a>
              <?php endforeach; ?>
            </dd>
          </div>
        <?php endif; ?>

        <?php if ($labels !== []): ?>
          <div class="item-meta-list__row">
            <dt>レーベル</dt>
            <dd>
              <?php foreach ($labels as $label): ?>
                <a href="<?= e(public_url('label.php') . '?id=' . rawurlencode((string)$label['id'])) ?>" class="link-subtle">
                  <?= e((string)$label['name']) ?>
                </a>
              <?php endforeach; ?>
            </dd>
          </div>
        <?php endif; ?>

        <?php if ($genres !== []): ?>
          <div class="item-meta-list__row">
            <dt>ジャンル</dt>
            <dd class="item-meta-list__tags">
              <?php foreach ($genres as $genre): ?>
                <a href="<?= e(public_url('genre.php') . '?id=' . rawurlencode((string)$genre['id'])) ?>" class="tag">
                  <?= e((string)$genre['name']) ?>
                </a>
              <?php endforeach; ?>
            </dd>
          </div>
        <?php endif; ?>
      </dl>

      <?php if (!empty($item['comment']) || !empty($item['description'])): ?>
        <div class="item-detail__desc">
          <h2 class="item-detail__desc-heading">作品説明</h2>
          <div class="item-detail__desc-body">
            <?= nl2br(e(strip_tags((string)(!empty($item['comment']) ? $item['comment'] : $item['description'])))) ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($relatedItems !== []): ?>
    <section class="block related-items" style="margin-top:40px;">
      <h2 class="section-title">関連作品</h2>
      <?php pcf_render_item_grid($relatedItems); ?>
    </section>
  <?php endif; ?>

  <section id="access-ranking" class="block pcf-item-ranking">
    <div class="pcf-item-ranking__heading">
      <div>
        <p class="pcf-item-ranking__eyebrow">ACCESS RANKING</p>
        <h2 class="section-title">人気の作品ランキング</h2>
      </div>
      <p class="pcf-item-ranking__description">閲覧数と元サイトへのアクセスをもとに集計しています。</p>
    </div>
  <nav class="pcf-item-ranking__tabs" aria-label="ランキング期間">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php $isActive = ($accessRankingPeriod === $tabKey); ?>
      <button type="button" class="pcf-item-ranking__tab<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?> data-rank-period="<?= e((string)$tabKey) ?>"><?= e((string)$tabConfig['label']) ?></button>
    <?php endforeach; ?>
  </nav>
  <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
    <?php
      $period = (string)$tabKey;
      $isActive = ($accessRankingPeriod === $period);
      $periodRows = $accessRankingRowsByPeriod[$period] ?? [];
    ?>
    <div class="pcf-item-ranking__panel" data-rank-panel="<?= e($period) ?>"<?= $isActive ? '' : ' style="display:none;"' ?>>
      <?php if ($periodRows !== []): ?>
        <ol class="pcf-item-ranking__list">
          <?php foreach ($periodRows as $index => $rankingRow): ?>
            <?php $rankingItemUrl = public_url('item.php') . '?id=' . rawurlencode((string)($rankingRow['id'] ?? '')); ?>
            <li class="pcf-item-ranking__row<?= $index < 3 ? ' is-top' : '' ?>">
              <span class="pcf-item-ranking__position"><?= e((string)($index + 1)) ?></span>
              <a class="pcf-item-ranking__title" href="<?= e($rankingItemUrl) ?>"><?= e((string)($rankingRow['title'] ?? '')) ?></a>
              <span class="pcf-item-ranking__metrics">
                <span>閲覧 <?= e((string)((int)($rankingRow['page_view_count'] ?? 0))) ?></span>
                <span>移動 <?= e((string)((int)($rankingRow['out_click_count'] ?? 0))) ?></span>
                <strong><?= e((string)((int)($rankingRow['access_count'] ?? 0))) ?> pt</strong>
              </span>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <?php pcf_render_empty('人気の作品ランキング！のデータがありません。'); ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <script>(() => {
    const s = document.getElementById('access-ranking');
    if (!s) return;
    const tabs = s.querySelectorAll('.pcf-item-ranking__tab[data-rank-period]');
    const panels = s.querySelectorAll('[data-rank-panel]');
    if (!tabs.length || !panels.length) return;
    tabs.forEach((tab) => {
      tab.addEventListener('click', (e) => {
        e.preventDefault();
        const p = tab.getAttribute('data-rank-period');
        tabs.forEach((t) => {
          const active = t === tab;
          t.classList.toggle('is-active', active);
          if (active) { t.setAttribute('aria-current', 'page'); } else { t.removeAttribute('aria-current'); }
        });
        panels.forEach((panel) => {
          panel.style.display = panel.getAttribute('data-rank-panel') === p ? '' : 'none';
        });
      });
    });
  })();</script>
  </section>
</article>

<div id="pcf-image-viewer-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:1200;">
  <button type="button" data-image-close="1" style="position:absolute; top:12px; right:16px; color:#fff; background:transparent; border:0; font-size:40px; line-height:1; cursor:pointer;">×</button>
  <div style="max-width:1200px; margin:26px auto 0; padding:0 18px;">
    <div style="display:flex; align-items:center; justify-content:center; min-height:66vh;">
      <img id="pcf-image-viewer-main" src="<?= e($packageImage) ?>" alt="サンプル画像" style="max-width:100%; max-height:66vh; object-fit:contain;">
    </div>
    <div id="pcf-image-viewer-thumbs" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:12px;"></div>
  </div>
</div>

<?php pcf_render_sample_movie_modal(); ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
