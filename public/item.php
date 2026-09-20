<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/../lib/public_rankings.php';
require_once __DIR__ . '/partials/public_ui.php';

function item_pick_detail_main_image(array $item): string
{
    foreach (['image_large', 'image_url_large', 'package_image', 'image_url', 'jacket_image_url'] as $key) {
        $candidate = trim((string)($item[$key] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function item_build_affiliate_out_url(array $item): string
{
    $affiliate = trim((string)($item['affiliate_url'] ?? ''));
    if ($affiliate === '') {
        return '#';
    }

    return public_url('out.php') . '?' . http_build_query(['to' => $affiliate]);
}

function item_is_invalid_actress_name(string $name): bool
{
    if (function_exists('pcf_is_noise_name') && pcf_is_noise_name($name)) {
        return true;
    }

    $value = mb_strtolower(trim($name), 'UTF-8');
    return $value === '' || $value === '--' || $value === '---';
}

$id = (int)get('id', 0);
$contentId = trim((string)get('content_id', ''));
$cid = trim((string)get('cid', ''));

if ($contentId === '' && $cid !== '') {
    $contentId = $cid;
}

$item = false;
try {
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM items WHERE id = :id AND ' . items_product_source_where() . ' LIMIT 1');
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
    }

    if (!$item && $contentId !== '') {
        $item = fetch_item_by_content_id($contentId);
    }

    if (!$item && $cid !== '') {
        $stmt = db()->prepare('SELECT * FROM items WHERE (content_id = :cid OR dmm_id = :cid) AND ' . items_product_source_where() . ' LIMIT 1');
        $stmt->execute([':cid' => $cid]);
        $item = $stmt->fetch();
    }
} catch (Throwable $e) {
    error_log('item lookup failed: ' . $e->getMessage());
    $item = false;
}

if (!$item || !is_array($item)) {
    require __DIR__ . '/404.php';
}

$db = null;
try {
    $db = db();
} catch (Throwable $e) {
    error_log('item page db bootstrap failed: ' . $e->getMessage());
}

$id = (int)$item['id'];
$itemId = $id;
$itemContentId = trim((string)($item['content_id'] ?? ''));
$actresses = [];
$genres = [];
$makers = [];
$series = [];
$labels = [];
if ($itemContentId !== '') {
    try {
        $actresses = fetch_item_actresses($itemContentId);
    } catch (Throwable $e) {
        error_log('item actress lookup failed: ' . $e->getMessage());
        $actresses = [];
    }
    try {
        $genres = fetch_item_genres($itemContentId);
    } catch (Throwable $e) {
        error_log('item genre lookup failed: ' . $e->getMessage());
        $genres = [];
    }
    try {
        $makers = fetch_item_makers($itemContentId);
    } catch (Throwable $e) {
        error_log('item maker lookup failed: ' . $e->getMessage());
        $makers = [];
    }
    try {
        $series = fetch_item_series($itemContentId);
    } catch (Throwable $e) {
        error_log('item series lookup failed: ' . $e->getMessage());
        $series = [];
    }
    if (db_table_exists('item_labels')) {
        try {
            $labels = fetch_item_labels($itemContentId);
        } catch (Throwable $e) {
            error_log('item label lookup failed: ' . $e->getMessage());
            $labels = [];
        }
    }
}

$directAffiliateUrl = trim((string)($item['affiliate_url'] ?? ''));
$affiliateUrl = item_build_affiliate_out_url($item);

$sampleMovieUrl = '';
if (!empty($item['sample_movie_url'])) {
    $sampleMovieUrl = (string)$item['sample_movie_url'];
} elseif (!empty($item['sample_movie_url_pc'])) {
    $sampleMovieUrl = (string)$item['sample_movie_url_pc'];
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
$sampleImagesCount = count($sampleImages);

$relatedItems = [];
if ($itemContentId !== '') {
    $relatedItems = dedupe_items_by_key(fetch_related_items($itemContentId, 12));
}

$title = (string)$item['title'];
$metaTitle = $title . ' - 作品詳細 | PinkClub';

$descBase = !empty($item['comment'])
    ? strip_tags((string)$item['comment'])
    : (!empty($item['description']) ? strip_tags((string)$item['description']) : $title);
$pageDescription = mb_strimwidth(trim(preg_replace('/\s+/u', ' ', $descBase)), 0, 150, '…', 'UTF-8');

$canonicalUrl = public_url('item.php') . '?id=' . rawurlencode((string)$id);

$packageImage = item_pick_detail_main_image($item);
$ogImage = $packageImage !== '' ? $packageImage : (!empty($item['image_url']) ? (string)$item['image_url'] : '');

$breadcrumbTitle = mb_strimwidth($title, 0, 24, '…', 'UTF-8');

$actressNames = [];
foreach ($actresses as $a) {
    $name = trim((string)($a['name'] ?? ''));
    if ($name !== '' && !item_is_invalid_actress_name($name)) {
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
