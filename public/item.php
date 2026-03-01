<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';

function item_decode_raw_json(array $item): array
{
    $raw = [];
    if (is_string($item['raw_json'] ?? null) && $item['raw_json'] !== '') {
        $decoded = json_decode((string)$item['raw_json'], true);
        if (is_array($decoded)) {
            $raw = $decoded;
        }
    }
    return $raw;
}

function item_collect_urls(mixed $value, array &$urls): void
{
    if (is_string($value)) {
        $candidate = trim($value);
        if ($candidate !== '' && (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://'))) {
            $urls[] = $candidate;
        }
        return;
    }
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $child) {
        item_collect_urls($child, $urls);
    }
}

function item_pick_sample_movie_url(array $item, array $raw): string
{
    foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $column) {
        $candidate = trim((string)($item[$column] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    foreach (['sampleMovieURL', 'sample_movie_url', 'sampleMovieUrl'] as $movieKeyName) {
        $rawMovie = $raw[$movieKeyName] ?? null;
        if (is_string($rawMovie) && trim($rawMovie) !== '') {
            return trim($rawMovie);
        }
        if (is_array($rawMovie)) {
            foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                $candidate = trim((string)($rawMovie[$movieKey] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
            $urls = [];
            item_collect_urls($rawMovie, $urls);
            if ($urls !== []) {
                return $urls[0];
            }
        }
    }

    return '';
}

function item_extract_sample_images(array $raw, int $max = 20): array
{
    $images = [];
    $sampleImageUrl = $raw['sampleImageURL'] ?? null;
    if (!is_array($sampleImageUrl)) {
        return [];
    }

    foreach (['sample_l', 'sample_s'] as $sampleKey) {
        $list = $sampleImageUrl[$sampleKey]['image'] ?? null;
        if (is_string($list)) {
            $list = [$list];
        }
        if (is_array($list)) {
            foreach ($list as $image) {
                $url = trim((string)$image);
                if ($url !== '') {
                    $images[] = $url;
                }
                if (count($images) >= $max) {
                    return array_values(array_unique($images));
                }
            }
        }
        if ($images !== []) {
            break;
        }
    }

    return array_values(array_unique($images));
}

function item_has_sample_images(array $item): bool
{
    $raw = item_decode_raw_json($item);
    return item_extract_sample_images($raw, 1) !== [];
}

function item_affiliate_link(array $item): string
{
    $affiliate = trim((string)($item['affiliate_url'] ?? ''));
    if ($affiliate !== '') {
        return $affiliate;
    }
    return trim((string)($item['url'] ?? ''));
}

$id = (int)get('id', 0);
$contentId = trim((string)get('content_id', ''));
$item = null;

try {
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
    } elseif ($contentId !== '') {
        $stmt = db()->prepare('SELECT * FROM items WHERE content_id = ?');
        $stmt->execute([$contentId]);
        $item = $stmt->fetch();
    }
} catch (Throwable $e) {
    error_log('public/item.php load item failed: ' . $e->getMessage());
}

if (!is_array($item)) {
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
        $insertView->execute([
            ':item_id' => (int)$item['id'],
            ':ip_hash' => $ipHash,
            ':user_agent' => $ua,
        ]);
    }
    update_items_view_count();
} catch (Throwable $e) {
    error_log('public/item.php page view logging failed: ' . $e->getMessage());
}

$raw = item_decode_raw_json($item);
$sampleMovieUrl = item_pick_sample_movie_url($item, $raw);
$sampleImages = item_extract_sample_images($raw, 20);
$affiliateLink = item_affiliate_link($item);

$coverImage = trim((string)($item['image_large'] ?? ''));
if ($coverImage === '') {
    $coverImage = trim((string)($item['image_small'] ?? ''));
}
if ($coverImage === '') {
    $coverImage = trim((string)($item['image_list'] ?? ''));
}

$relatedItems = [];
try {
    $relatedItems = fetch_related_items((string)$item['content_id'], 12);
} catch (Throwable $e) {
    error_log('public/item.php related load failed: ' . $e->getMessage());
}

$title = (string)$item['title'];
require __DIR__ . '/partials/header.php';
?>

<section class="item-page">
  <div class="card item-top-video">
    <h2>サンプル動画</h2>
    <div class="item-top-video__placeholder"><?= $sampleMovieUrl !== '' ? 'サンプル動画を視聴できます。' : 'サンプル動画はありません。' ?></div>
    <button type="button" class="sample-button <?= $sampleMovieUrl !== '' ? 'sample-button--enabled' : 'sample-button--disabled' ?>" <?= $sampleMovieUrl === '' ? 'disabled' : '' ?> onclick="<?= $sampleMovieUrl !== '' ? "window.open('" . e($sampleMovieUrl) . "','_blank','noopener,noreferrer');" : 'return false;' ?>">サンプル動画を見る</button>
  </div>

  <div class="card item-main-block">
    <div class="item-main-block__image">
      <?php if ($coverImage !== ''): ?>
        <img class="thumb" src="<?= e($coverImage) ?>" alt="<?= e((string)$item['title']) ?>">
      <?php else: ?>
        <div class="item-main-block__noimage">画像なし</div>
      <?php endif; ?>
    </div>
    <div class="item-main-block__info">
      <h1><?= e((string)$item['title']) ?></h1>
      <ul>
        <li>発売日: <?= e((string)($item['release_date'] ?? $item['date_published'] ?? '')) ?></li>
        <li>収録時間: <?= e((string)($item['runtime'] ?? '不明')) ?></li>
        <li>価格: <?= e((string)($item['price_min_text'] ?? $item['price_min'] ?? '')) ?></li>
        <li>レビュー: <?= e((string)($item['review_average'] ?? '-')) ?> (<?= e((string)($item['review_count'] ?? '0')) ?>)</li>
      </ul>
    </div>
  </div>

  <div class="card item-affiliate-block">
    <button type="button" class="sample-button item-affiliate-block__button <?= $affiliateLink !== '' ? 'sample-button--enabled' : 'sample-button--disabled' ?>" <?= $affiliateLink === '' ? 'disabled' : '' ?> onclick="<?= $affiliateLink !== '' ? "window.open('" . e($affiliateLink) . "','_blank','noopener,noreferrer');" : 'return false;' ?>">アフィリエイトリンク</button>
  </div>

  <div class="card item-sample-images-block">
    <h2>サンプル画像</h2>
    <?php if ($sampleImages === []): ?>
      <p>サンプル画像はありません。</p>
    <?php else: ?>
      <div class="item-sample-images-grid">
        <?php foreach ($sampleImages as $index => $image): ?>
          <button type="button" class="item-sample-image" onclick="window.open('<?= e(public_url('sample_images.php?content_id=' . rawurlencode((string)$item['content_id']))) ?>#image-<?= (int)($index + 1) ?>', '_blank', 'noopener,noreferrer')">
            <img src="<?= e($image) ?>" alt="サンプル画像 <?= e((string)($index + 1)) ?>">
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card item-affiliate-block">
    <button type="button" class="sample-button item-affiliate-block__button <?= $affiliateLink !== '' ? 'sample-button--enabled' : 'sample-button--disabled' ?>" <?= $affiliateLink === '' ? 'disabled' : '' ?> onclick="<?= $affiliateLink !== '' ? "window.open('" . e($affiliateLink) . "','_blank','noopener,noreferrer');" : 'return false;' ?>">購入する</button>
  </div>

  <?php if ($relatedItems !== []): ?>
    <section class="rail-section">
      <h2>関連商品</h2>
      <div class="rail-row rail-row--180">
        <?php foreach ($relatedItems as $related): ?>
          <?php
            $relatedRaw = item_decode_raw_json($related);
            $relatedMovie = item_pick_sample_movie_url($related, $relatedRaw);
            $relatedHasImages = item_has_sample_images($related);
            $relatedAffiliate = item_affiliate_link($related);
          ?>
          <article class="card rail-card rail-card--180">
            <?php if (!empty($related['image_small'])): ?>
              <img class="thumb" src="<?= e((string)$related['image_small']) ?>" alt="<?= e((string)($related['title'] ?? '')) ?>">
            <?php else: ?>
              <div class="rail-card__noimage">画像なし</div>
            <?php endif; ?>
            <a class="rail-card__title" href="<?= e(public_url('item.php?id=' . (int)$related['id'])) ?>"><?= e((string)($related['title'] ?? '')) ?></a>
            <div class="sample-buttons">
              <button type="button" class="sample-button <?= $relatedMovie !== '' ? 'sample-button--enabled' : 'sample-button--disabled' ?>" <?= $relatedMovie === '' ? 'disabled' : '' ?> onclick="<?= $relatedMovie !== '' ? "window.open('" . e($relatedMovie) . "','_blank','noopener,noreferrer');" : 'return false;' ?>">サンプル動画</button>
              <button type="button" class="sample-button <?= $relatedHasImages ? 'sample-button--enabled' : 'sample-button--disabled' ?>" <?= !$relatedHasImages ? 'disabled' : '' ?> onclick="<?= $relatedHasImages ? "window.open('" . e(public_url('sample_images.php?content_id=' . rawurlencode((string)$related['content_id']))) . "','_blank','noopener,noreferrer');" : 'return false;' ?>">サンプル画像</button>
              <button type="button" class="sample-button <?= $relatedAffiliate !== '' ? 'sample-button--enabled' : 'sample-button--disabled' ?>" <?= $relatedAffiliate === '' ? 'disabled' : '' ?> onclick="<?= $relatedAffiliate !== '' ? "window.open('" . e($relatedAffiliate) . "','_blank','noopener,noreferrer');" : 'return false;' ?>">アフィリエイトリンク</button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
