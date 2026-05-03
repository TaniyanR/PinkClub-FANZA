<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';
require_once __DIR__ . '/partials/public_ui.php';

function item_normalize_movie_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }

    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
        return $url;
    }

    return '';
}

function item_collect_movie_urls(mixed $value, array &$urls): void
{
    if (is_string($value)) {
        $candidate = item_normalize_movie_url($value);
        if ($candidate !== '') {
            $urls[] = $candidate;
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $child) {
        item_collect_movie_urls($child, $urls);
    }
}

function item_pick_movie_urls_from_raw(array $raw): array
{
    $urls = [];
    foreach (['sampleMovieURL', 'sample_movie_url', 'sampleMovieUrl'] as $movieKeyName) {
        $rawMovie = $raw[$movieKeyName] ?? null;

        if (is_string($rawMovie)) {
            $candidate = item_normalize_movie_url($rawMovie);
            if ($candidate !== '') {
                $urls[] = $candidate;
            }
        }

        if (is_array($rawMovie)) {
            foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                $candidate = item_normalize_movie_url((string)($rawMovie[$movieKey] ?? ''));
                if ($candidate !== '') {
                    $urls[] = $candidate;
                }
            }

            item_collect_movie_urls($rawMovie, $urls);
        }
    }

    return array_values(array_unique(array_filter(array_map(static fn($u) => trim((string)$u), $urls))));
}

function item_pick_raw_text(array $raw, array $keys): string
{
    foreach ($keys as $key) {
        $value = $raw[$key] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
        if (is_array($value)) {
            foreach ($value as $child) {
                if (is_string($child) && trim($child) !== '') {
                    return trim($child);
                }
                if (is_array($child)) {
                    foreach (['value', 'text', 'name'] as $childKey) {
                        if (isset($child[$childKey]) && is_string($child[$childKey]) && trim($child[$childKey]) !== '') {
                            return trim((string)$child[$childKey]);
                        }
                    }
                }
            }
        }
    }

    return '';
}


function item_collect_named_values(mixed $value, array &$values): void
{
    if (is_string($value)) {
        $text = trim((string)$value);
        if ($text !== '') {
            $values[] = $text;
        }
        return;
    }
    if (!is_array($value)) {
        return;
    }
    if (isset($value['name']) && is_string($value['name'])) {
        $name = trim((string)$value['name']);
        if ($name !== '') {
            $values[] = $name;
        }
    }
    foreach ($value as $child) {
        item_collect_named_values($child, $values);
    }
}


function item_is_invalid_description(string $text): bool
{
    $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($normalized === '') {
        return true;
    }

    return str_contains($normalized, 'ここから先は、アダルト商品を扱うアダルトサイトとなります')
        || str_contains($normalized, '18歳未満の方のアクセスは固くお断りします')
        || str_contains($normalized, '年齢認証')
        || str_contains($normalized, 'adult only');
}

function item_is_invalid_title(string $title): bool
{
    $normalized = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($normalized === '') {
        return true;
    }

    return str_contains($normalized, 'お問い合わせ')
        || str_contains($normalized, '問合せ')
        || $normalized === 'Privacy Policy'
        || $normalized === 'サイトについて';
}

function item_unique_rows(array $rows, array $keys): array
{
    $unique = [];
    $seen = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $parts = [];
        foreach ($keys as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        if ($parts === []) {
            $unique[] = $row;
            continue;
        }

        $signature = implode('|', $parts);
        if (isset($seen[$signature])) {
            continue;
        }

        $seen[$signature] = true;
        $unique[] = $row;
    }

    return $unique;
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
        $stmt = db()->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (is_array($item)) {
            $itemTitle = trim((string)($item['title'] ?? ''));
            if (item_is_invalid_title($itemTitle)) {
                $itemByContentId = fetch_item_by_content_id((string)($item['content_id'] ?? ''));
                if (is_array($itemByContentId)) {
                    $item = $itemByContentId;
                }
            }
        }
    } elseif ($contentId !== '') {
        $item = fetch_item_by_content_id($contentId);
    }
} catch (Throwable) {
    $item = false;
}

if (!$item) {
    http_response_code(404);
    exit('not found');
}

try {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = $ip !== '' ? hash('sha256', $ip . date('Y-m-d')) : null;
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $viewStmt = db()->prepare('SELECT id FROM page_views WHERE item_id = :item_id AND ip_hash = :ip_hash AND DATE(viewed_at) = CURDATE() LIMIT 1');
    $viewStmt->execute([':item_id' => (int)$item['id'], ':ip_hash' => $ipHash]);
    if (!$viewStmt->fetch()) {
        $insertView = db()->prepare('INSERT INTO page_views (item_id, viewed_at, ip_hash, user_agent) VALUES (:item_id, NOW(), :ip_hash, :user_agent)');
        $insertView->execute([':item_id' => (int)$item['id'], ':ip_hash' => $ipHash, ':user_agent' => $ua]);
    }
} catch (Throwable $e) {
    error_log('page view logging failed: ' . $e->getMessage());
}

$relatedItems = [];
$actresses = [];
$genres = [];
$makers = [];
$seriesList = [];
$authors = [];

try {
    update_items_view_count();
} catch (Throwable) {
}

try {
    $relatedItems = fetch_related_items((string)$item['content_id'], 12);
} catch (Throwable) {
    $relatedItems = [];
}
try {
    $actresses = fetch_item_actresses((string)$item['content_id']);
} catch (Throwable) {
    $actresses = [];
}
try {
    $genres = fetch_item_genres((string)$item['content_id']);
} catch (Throwable) {
    $genres = [];
}
try {
    $makers = fetch_item_makers((string)$item['content_id']);
} catch (Throwable) {
    $makers = [];
}
try {
    $seriesList = fetch_item_series((string)$item['content_id']);
} catch (Throwable) {
    $seriesList = [];
}

if (db_table_exists('item_authors')) {
    try {
        $authorStmt = db()->prepare('SELECT author_id AS id, author_name AS name FROM item_authors WHERE content_id = ? ORDER BY author_name');
        $authorStmt->execute([(string)$item['content_id']]);
        $authors = $authorStmt->fetchAll() ?: [];
    } catch (Throwable) {
        $authors = [];
    }
}

$relatedItems = item_unique_rows($relatedItems, ['content_id']);
$actresses = item_unique_rows($actresses, ['id', 'name']);
$genres = item_unique_rows($genres, ['id', 'name']);
$makers = item_unique_rows($makers, ['id', 'name']);
$seriesList = item_unique_rows($seriesList, ['id', 'name']);
$authors = item_unique_rows($authors, ['id', 'name']);

$actresses = array_values(array_filter($actresses, static function ($row): bool {
    return is_array($row) && !pcf_is_noise_name((string)($row['name'] ?? ''));
}));
$genres = array_values(array_filter($genres, static function ($row): bool {
    return is_array($row) && !pcf_is_noise_name((string)($row['name'] ?? ''));
}));
$makers = array_values(array_filter($makers, static function ($row): bool {
    return is_array($row) && !pcf_is_noise_name((string)($row['name'] ?? ''));
}));
$seriesList = array_values(array_filter($seriesList, static function ($row): bool {
    return is_array($row) && !pcf_is_noise_name((string)($row['name'] ?? ''));
}));
$authors = array_values(array_filter($authors, static function ($row): bool {
    return is_array($row) && !pcf_is_noise_name((string)($row['name'] ?? ''));
}));

$raw = [];
if (is_string($item['raw_json'] ?? null) && $item['raw_json'] !== '') {
    $decoded = json_decode($item['raw_json'], true);
    if (is_array($decoded)) {
        $raw = $decoded;
    }
}

$sampleMovieUrl = '';
foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $movieColumn) {
    $candidate = trim((string)($item[$movieColumn] ?? ''));
    if ($candidate !== '') {
        $sampleMovieUrl = item_normalize_movie_url($candidate);
        break;
    }
}
if ($sampleMovieUrl === '') {
    $sampleMovieUrls = item_pick_movie_urls_from_raw($raw);
    $sampleMovieUrl = (string)($sampleMovieUrls[0] ?? '');
}

$sampleImages = [];
$sampleImagesSmall = [];
$sampleImageUrl = $raw['sampleImageURL'] ?? null;
if (is_array($sampleImageUrl)) {
    $imagesLarge = $sampleImageUrl['sample_l']['image'] ?? null;
    if (is_array($imagesLarge)) {
        foreach ($imagesLarge as $image) {
            $url = trim((string)($image ?? ''));
            if ($url !== '') {
                $sampleImages[] = $url;
            }
        }
    }
    $imagesSmall = $sampleImageUrl['sample_s']['image'] ?? null;
    if (is_array($imagesSmall)) {
        foreach ($imagesSmall as $image) {
            $url = trim((string)($image ?? ''));
            if ($url !== '') {
                $sampleImagesSmall[] = $url;
            }
        }
    }
}
$sampleImages = array_values(array_unique($sampleImages));
$sampleImagesSmall = array_values(array_unique($sampleImagesSmall));
$sampleImagesSmallLargeMap = [];
foreach ($sampleImagesSmall as $i => $smallImage) {
    $largeImage = (string)($sampleImages[$i] ?? $smallImage);
    $sampleImagesSmallLargeMap[] = ['small' => (string)$smallImage, 'large' => $largeImage];
}

$fullPackageImage = trim((string)($item['image_large'] ?? ''));
if ($fullPackageImage === '') {
    $imageListRaw = (string)($item['image_list'] ?? '');
    $imageList = preg_split('/[\r\n,|\s]+/', $imageListRaw);
    if (is_array($imageList)) {
        foreach ($imageList as $imageListValue) {
            $candidate = trim((string)$imageListValue);
            if ($candidate !== '') {
                $fullPackageImage = $candidate;
                break;
            }
        }
    }
}

$desc = trim((string)($item['description'] ?? ''));
if ($desc === '') {
    $desc = item_pick_raw_text($raw, ['comment', 'description', 'caption', 'story', 'introduction']);
}
if ($desc === '') {
    $desc = item_pick_raw_text((array)($raw['iteminfo'] ?? []), ['comment', 'description', 'caption', 'story', 'introduction']);
}
if ($desc === '') {
    try {
        $articleStmt = db()->prepare('SELECT description FROM articles WHERE product_id IN (?, ?) ORDER BY id DESC LIMIT 1');
        $articleStmt->execute([(string)($item['content_id'] ?? ''), (string)($item['product_id'] ?? '')]);
        $articleRow = $articleStmt->fetch();
        if (is_array($articleRow)) {
            $desc = trim((string)($articleRow['description'] ?? ''));
        }
        if ($desc === '') {
            $articleTitle = trim((string)($item['title'] ?? ''));
            if ($articleTitle !== '') {
                $articleByTitleStmt = db()->prepare('SELECT description FROM articles WHERE title = ? ORDER BY id DESC LIMIT 1');
                $articleByTitleStmt->execute([$articleTitle]);
                $articleByTitleRow = $articleByTitleStmt->fetch();
                if (is_array($articleByTitleRow)) {
                    $desc = trim((string)($articleByTitleRow['description'] ?? ''));
                }
            }
        }
    } catch (Throwable) {
    }
}
if (item_is_invalid_description($desc)) {
    $desc = '';
}

$titleCandidates = [
    (string)($item['title'] ?? ''),
    trim((string)($raw['title'] ?? '')),
    trim((string)($raw['name'] ?? '')),
    trim((string)($raw['productTitle'] ?? '')),
    trim((string)($raw['iteminfo']['title'][0]['name'] ?? '')),
    trim((string)($raw['iteminfo']['title'][0]['value'] ?? '')),
];
$title = '商品詳細';
foreach ($titleCandidates as $candidateTitle) {
    $candidateTitle = trim((string)$candidateTitle);
    if ($candidateTitle === '') {
        continue;
    }
    if (item_is_invalid_title($candidateTitle)) {
        continue;
    }
    $title = $candidateTitle;
    break;
}
if ($title === '商品詳細') {
    $title = trim((string)($item['content_id'] ?? '')) !== '' ? (string)$item['content_id'] : '商品詳細';
}
$breadcrumbTitle = $title;
if (item_is_invalid_title($breadcrumbTitle)) {
    $breadcrumbTitle = item_pick_raw_text((array)($raw['iteminfo'] ?? []), ['title']);
}
if (item_is_invalid_title($breadcrumbTitle) || trim($breadcrumbTitle) === '') {
    $breadcrumbTitle = trim((string)($item['product_id'] ?? ''));
}
if (item_is_invalid_title($breadcrumbTitle) || trim($breadcrumbTitle) === '') {
    $breadcrumbTitle = trim((string)($item['content_id'] ?? ''));
}
if (trim($breadcrumbTitle) === '') {
    $breadcrumbTitle = '商品詳細';
}
$affiliateUrl = trim((string)($item['affiliate_url'] ?? ''));
$rawMakerName = item_pick_raw_text((array)($raw['iteminfo'] ?? []), ['maker', 'label']);
$rawSeriesName = item_pick_raw_text((array)($raw['iteminfo'] ?? []), ['series']);
$rawDirectorName = item_pick_raw_text((array)($raw['iteminfo'] ?? []), ['director']);
$deviceText = item_pick_raw_text($raw, ['supportedDevices', 'device', 'devices']);
$deliveryStartText = item_pick_raw_text($raw, ['date', 'deliveryStartDate', 'delivery_start_date']);
$labelName = item_pick_raw_text((array)($raw['iteminfo'] ?? []), ['label']);
$performerText = implode('、', array_values(array_filter(array_map(static fn($v) => trim((string)($v['name'] ?? '')), $actresses), static fn($v) => $v !== '')));
$genreText = implode('、', array_values(array_filter(array_map(static fn($v) => trim((string)($v['name'] ?? '')), $genres), static fn($v) => $v !== '')));
$tagText = item_pick_raw_text($raw, ['tag', 'tags']);
if ($tagText === '') {
    $tagValues = [];
    item_collect_named_values($raw['iteminfo']['genre'] ?? [], $tagValues);
    $tagValues = array_values(array_unique(array_filter(array_map(static fn($v) => trim((string)$v), $tagValues), static fn($v) => $v !== '')));
    if ($tagValues !== []) {
        $tagText = implode(' ', array_map(static fn($v) => str_starts_with($v, '#') ? $v : ('#' . $v), $tagValues));
    }
}
if ($deviceText === '') {
    $pcFlag = (int)($raw['sampleMovieURL']['pc_flag'] ?? $item['sample_movie_pc_flag'] ?? 0);
    $spFlag = (int)($raw['sampleMovieURL']['sp_flag'] ?? $item['sample_movie_sp_flag'] ?? 0);
    if ($pcFlag === 1 && $spFlag === 1) {
        $deviceText = 'PC / スマホ';
    } elseif ($pcFlag === 1) {
        $deviceText = 'PC';
    } elseif ($spFlag === 1) {
        $deviceText = 'スマホ';
    }
}
if ($tagText === '') {
    $tagText = '';
}
$releaseDateDisplay = (string)format_date((string)($item['release_date'] ?? ''));
if ($releaseDateDisplay === '') {
    $releaseDateDisplay = (string)format_date((string)($raw['date'] ?? ''));
}
$deliveryStartDisplay = (string)format_date((string)$deliveryStartText);
if ($deliveryStartDisplay === '') {
    $deliveryStartDisplay = trim((string)$deliveryStartText);
}
$volumeDisplay = (string)($item['volume'] ?? '');
if (trim($volumeDisplay) === '') {
    $volumeDisplay = trim((string)($raw['volume'] ?? ''));
}
if ($volumeDisplay !== '' && !str_contains($volumeDisplay, '分')) {
    if (preg_match('/^\d+$/', $volumeDisplay) === 1) {
        $volumeDisplay .= '分';
    }
}
$contentIdDisplay = trim((string)($item['content_id'] ?? ''));
if ($contentIdDisplay === '') {
    $contentIdDisplay = trim((string)($raw['content_id'] ?? ''));
}
$productIdDisplay = trim((string)($raw['maker_product'] ?? ''));
if ($productIdDisplay === '') {
    $productIdDisplay = trim((string)($item['product_id'] ?? ''));
}
if ($productIdDisplay === '') {
    $productIdDisplay = trim((string)($raw['product_id'] ?? ''));
}
$packageImage = pcf_item_image(is_array($item) ? $item : []);
if (str_starts_with($packageImage, 'data:image/svg+xml')) {
    $packageImage = '';
}
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_breadcrumbs([
    ['label' => 'トップ', 'url' => public_url('index.php')],
    ['label' => '商品一覧', 'url' => public_url('items.php')],
    ['label' => $breadcrumbTitle],
]); ?>

<article>
  <h1 class="pcf-hero__title"><?= e($breadcrumbTitle) ?></h1>

  <?php if ($sampleMovieUrl !== '' || $sampleImagesSmallLargeMap !== []): ?>
    <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
      <?php if ($sampleMovieUrl !== ''): ?>
      <div class="sample-movie-modal__frame-wrap" style="width: 720px; max-width: 100%; aspect-ratio: 720 / 480;">
        <iframe class="sample-movie-modal__frame" src="<?= e($sampleMovieUrl) ?>" allow="autoplay; fullscreen" referrerpolicy="no-referrer" scrolling="no" width="720" height="480"></iframe>
      </div>
      <?php endif; ?>
      <?php if ($sampleImagesSmallLargeMap !== []): ?>
      <div style="width:196px; max-width:100%; height:480px; overflow:hidden;"><div style="display:grid; grid-template-rows:repeat(6, 72px); grid-auto-flow:column; grid-auto-columns:92px; gap:8px; align-content:start;">
        <?php foreach ($sampleImagesSmallLargeMap as $i => $imagePair): ?>
          <a href="<?= e((string)$imagePair['large']) ?>" class="pcf-image-viewer-trigger" data-image-index="<?= e((string)$i) ?>" style="display:block;">
            <img src="<?= e((string)$imagePair['small']) ?>" alt="サンプル画像 <?= e((string)($i + 1)) ?>" loading="lazy" style="display:block; width:100%; height:72px; object-fit:cover;">
          </a>
        <?php endforeach; ?>
      </div></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($affiliateUrl !== ''): ?>
    <p><a class="pcf-btn" style="display:block; text-align:center; border:2px solid #9aa0ab; font-weight:700; font-size:18px; padding:12px 14px;" href="<?= e($affiliateUrl) ?>" target="_blank" rel="noopener noreferrer">購入ボタン</a></p>
  <?php endif; ?>

  <section class="pcf-detail pcf-item-main">
    <div class="pcf-item-main__media">
      <?php if ($packageImage !== ''): ?>
      <a href="<?= e($packageImage) ?>" target="_blank" rel="noopener noreferrer">
        <img class="pcf-detail__package" src="<?= e($packageImage) ?>" alt="<?= e((string)($item['title'] ?? '')) ?>">
      </a>
      <?php endif; ?>
      <?php if ($desc !== ''): ?><p><?= nl2br(e($desc)) ?></p><?php endif; ?>
    </div>

    <div class="pcf-item-main__info">
      <ul class="pcf-item-card__meta" style="color:#000 !important; font-size:14px;">
        <li>対応デバイス: <?= e($deviceText !== '' ? $deviceText : '―') ?></li>
        <li>配信開始日: <?= e($deliveryStartDisplay !== '' ? $deliveryStartDisplay : '―') ?></li>
        <li>商品発売日: <?= e($releaseDateDisplay !== '' ? $releaseDateDisplay : '―') ?></li>
        <li>収録時間: <?= e($volumeDisplay !== '' ? $volumeDisplay : '―') ?></li>
        <li>出演者: <?= e($performerText !== '' ? $performerText : '―') ?></li>
        <li>監督: <?= e($rawDirectorName !== '' ? $rawDirectorName : '―') ?></li>
        <li>シリーズ: <?= e($rawSeriesName !== '' ? $rawSeriesName : '―') ?></li>
        <li>メーカー: <?= e($rawMakerName !== '' ? $rawMakerName : '―') ?></li>
        <li>レーベル: <?= e($labelName !== '' ? $labelName : '―') ?></li>
        <li>ジャンル: <?= e($genreText !== '' ? $genreText : '―') ?></li>
        <li>関連タグ: <?= e($tagText !== '' ? $tagText : '―') ?></li>
        <li>配信品番: <?= e($contentIdDisplay !== '' ? $contentIdDisplay : '―') ?></li>
        <li>メーカー品番: <?= e($productIdDisplay !== '' ? $productIdDisplay : '―') ?></li>
      </ul>

    </div>
  </section>

  <?php if ($affiliateUrl !== ''): ?>
    <p><a class="pcf-btn" style="display:block; text-align:center; border:2px solid #9aa0ab; font-weight:700; font-size:18px; padding:12px 14px;" href="<?= e($affiliateUrl) ?>" target="_blank" rel="noopener noreferrer">購入ボタン</a></p>
  <?php endif; ?>

  <h2 class="pcf-section-title">関連作品</h2>
  <?php if ($relatedItems !== []): ?>
    <section class="pcf-related-grid">
      <?php foreach ($relatedItems as $related): pcf_render_item_card(is_array($related) ? $related : []); endforeach; ?>
    </section>
  <?php else: ?>
    <?php pcf_render_empty('関連作品はありません。'); ?>
  <?php endif; ?>
</article>

<div id="pcf-image-viewer-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:1200;">
  <button type="button" data-image-close="1" style="position:absolute; top:12px; right:16px; color:#fff; background:transparent; border:0; font-size:40px; line-height:1; cursor:pointer;">×</button>
  <div style="max-width:1200px; margin:26px auto 0; padding:0 18px;">
    <div style="display:flex; align-items:center; justify-content:center; min-height:66vh;">
      <img id="pcf-image-viewer-main" src="" alt="サンプル画像" style="max-width:100%; max-height:66vh; object-fit:contain;">
    </div>
    <div id="pcf-image-viewer-thumbs" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:12px;"></div>
  </div>
</div>

<div id="sample-movie-modal" class="sample-movie-modal" aria-hidden="true">
  <div class="sample-movie-modal__overlay" data-movie-close="1"></div>
  <div class="sample-movie-modal__dialog" role="dialog" aria-modal="true" aria-label="サンプル動画プレイヤー">
    <button type="button" class="sample-movie-modal__close" data-movie-close="1" aria-label="閉じる">×</button>
    <div id="sample-movie-title" class="sample-movie-modal__title">サンプル動画</div>
    <div class="sample-movie-modal__frame-wrap">
      <iframe id="sample-movie-frame" class="sample-movie-modal__frame" src="about:blank" allow="autoplay; fullscreen" referrerpolicy="no-referrer"></iframe>
    </div>
  </div>
</div>
<script>
(() => {
  const modal = document.getElementById('sample-movie-modal');
  const frame = document.getElementById('sample-movie-frame');
  const titleNode = document.getElementById('sample-movie-title');
  const openMovie = (url, title) => {
    if (!modal || !frame || !titleNode || !url) return;
    titleNode.textContent = title || 'サンプル動画';
    frame.src = url;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  };

  const imageViewer = document.getElementById('pcf-image-viewer-modal');
  const imageViewerMain = document.getElementById('pcf-image-viewer-main');
  const imageViewerThumbs = document.getElementById('pcf-image-viewer-thumbs');
  const imageList = <?= json_encode(array_map(static fn($pair) => ['small' => (string)($pair['small'] ?? ''), 'large' => (string)($pair['large'] ?? '')], $sampleImagesSmallLargeMap), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const showImage = (index) => {
    if (!imageViewerMain || !imageViewerThumbs || !Array.isArray(imageList) || imageList.length === 0) return;
    const idx = Math.max(0, Math.min(index, imageList.length - 1));
    imageViewerMain.src = imageList[idx].large || imageList[idx].small || '';
    imageViewerThumbs.innerHTML = '';
    imageList.forEach((item, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.style.border = i === idx ? '2px solid #ff6b6b' : '1px solid #777';
      btn.style.padding = '0';
      btn.style.background = 'transparent';
      btn.style.cursor = 'pointer';
      const img = document.createElement('img');
      img.src = item.small || item.large || '';
      img.alt = 'サムネイル ' + (i + 1);
      img.style.width = '76px';
      img.style.height = '50px';
      img.style.objectFit = 'cover';
      img.style.display = 'block';
      btn.appendChild(img);
      btn.addEventListener('click', () => showImage(i));
      imageViewerThumbs.appendChild(btn);
    });
  };
  const openImageViewer = (index) => {
    if (!imageViewer) return;
    showImage(index);
    imageViewer.style.display = 'block';
  };
  const closeImageViewer = () => {
    if (!imageViewer || !imageViewerMain) return;
    imageViewer.style.display = 'none';
    imageViewerMain.src = '';
  };

  const closeMovie = () => {
    if (!modal || !frame || !titleNode) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    frame.src = 'about:blank';
  };
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.sample-movie-trigger');
    if (trigger) { event.preventDefault(); openMovie(trigger.dataset.movieUrl || '', trigger.dataset.movieTitle || ''); return; }
    const imageTrigger = event.target.closest('.pcf-image-viewer-trigger');
    if (imageTrigger) { event.preventDefault(); openImageViewer(parseInt(imageTrigger.dataset.imageIndex || '0', 10)); return; }
    if (event.target.closest('[data-image-close="1"]')) { event.preventDefault(); closeImageViewer(); return; }
    if (event.target.closest('[data-movie-close="1"]')) { event.preventDefault(); closeMovie(); }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) closeMovie();
  });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
