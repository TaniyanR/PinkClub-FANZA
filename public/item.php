<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';

$id = (int)get('id', 0);
$contentId = trim((string)get('content_id', ''));

if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM items WHERE id = ?');
    $stmt->execute([$id]);
} elseif ($contentId !== '') {
    $stmt = db()->prepare('SELECT * FROM items WHERE content_id = ?');
    $stmt->execute([$contentId]);
} else {
    http_response_code(404);
    exit('not found');
}

$item = $stmt->fetch();
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
        $insertView->execute([
            ':item_id' => (int)$item['id'],
            ':ip_hash' => $ipHash,
            ':user_agent' => $ua,
        ]);
    }
} catch (Throwable $e) {
    error_log('page view logging failed: ' . $e->getMessage());
}

update_items_view_count();
$relatedItems = fetch_related_items((string)$item['content_id'], 12);

$rels = [];
foreach ([
    'item_actresses' => 'actress_name',
    'item_genres' => 'genre_name',
    'item_labels' => 'label_name',
    'item_campaigns' => 'campaign_name',
    'item_directors' => 'director_name',
    'item_makers' => 'maker_name',
    'item_series' => 'series_name',
    'item_authors' => 'author_name',
    'item_actors' => 'actor_name',
] as $t => $c) {
    $s = db()->prepare("SELECT {$c} FROM {$t} WHERE item_id = ?");
    $s->execute([(int)$item['id']]);
    $rels[$c] = $s->fetchAll(PDO::FETCH_COLUMN);
}

$raw = [];
if (is_string($item['raw_json'] ?? null) && $item['raw_json'] !== '') {
    $decoded = json_decode($item['raw_json'], true);
    if (is_array($decoded)) {
        $raw = $decoded;
    }
}

function normalize_movie_url_item(string $url): string
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

function collect_movie_urls_from_value_item(mixed $value, array &$urls): void
{
    if (is_string($value)) {
        $candidate = normalize_movie_url_item($value);
        if ($candidate !== '') {
            $urls[] = $candidate;
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $child) {
        collect_movie_urls_from_value_item($child, $urls);
    }
}

function pick_sample_movie_url_from_raw_item(array $raw): string
{
    foreach (['sampleMovieURL', 'sample_movie_url', 'sampleMovieUrl'] as $movieKeyName) {
        $rawMovie = $raw[$movieKeyName] ?? null;

        if (is_string($rawMovie)) {
            $candidate = normalize_movie_url_item($rawMovie);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        if (is_array($rawMovie)) {
            foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                $candidate = normalize_movie_url_item((string)($rawMovie[$movieKey] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            $urls = [];
            collect_movie_urls_from_value_item($rawMovie, $urls);
            if ($urls !== []) {
                return $urls[0];
            }
        }
    }

    return '';
}

$sampleMovieUrl = '';
foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $movieColumn) {
    $candidate = normalize_movie_url_item((string)($item[$movieColumn] ?? ''));
    if ($candidate !== '') {
        $sampleMovieUrl = $candidate;
        break;
    }
}

if ($sampleMovieUrl === '') {
    $sampleMovieUrl = pick_sample_movie_url_from_raw_item($raw);
}

$sampleImages = [];
$sampleImageUrl = $raw['sampleImageURL'] ?? null;
if (is_array($sampleImageUrl)) {
    foreach (['sample_l', 'sample_s'] as $sampleKey) {
        $images = $sampleImageUrl[$sampleKey]['image'] ?? null;
        if (is_array($images)) {
            foreach ($images as $image) {
                $url = trim((string)($image ?? ''));
                if ($url !== '') {
                    $sampleImages[] = $url;
                }
            }
            if ($sampleImages !== []) {
                break;
            }
        }
    }
}

$packageImage = '';
foreach (['image_large', 'image_list', 'image_small'] as $imgCol) {
    $candidate = trim((string)($item[$imgCol] ?? ''));
    if ($candidate !== '') {
        $packageImage = $candidate;
        break;
    }
}

$affiliateUrl = trim((string)($item['affiliate_url'] ?? ''));

$desc = '';
foreach (['comment', 'description', 'caption'] as $descKey) {
    if (isset($raw[$descKey]) && is_string($raw[$descKey]) && trim($raw[$descKey]) !== '') {
        $desc = trim($raw[$descKey]);
        break;
    }
}
if ($desc === '') {
    $desc = trim((string)($item['description'] ?? ''));
}

title = (string)$item['title'];
require __DIR__ . '/partials/header.php';
?>

<article class="item-detail">
  <h2 class="item-detail__title"><?= e((string)$item['title']) ?></h2>

  <?php if ($sampleMovieUrl !== ''): ?>
    <section class="item-detail__movie">
      <button type="button" class="sample-movie-trigger item-detail__movie-trigger" data-movie-url="<?= e($sampleMovieUrl) ?>" data-movie-title="<?= e((string)$item['title']) ?>">
        サンプル動画を再生
      </button>
    </section>
  <?php endif; ?>

  <section class="item-detail__top">
    <div class="item-detail__left">
      <?php if ($packageImage !== ''): ?>
        <img class="item-detail__package" src="<?= e($packageImage) ?>" alt="<?= e((string)$item['title']) ?>">
      <?php else: ?>
        <div class="item-detail__package item-detail__package--noimage">画像なし</div>
      <?php endif; ?>

      <?php if ($affiliateUrl !== ''): ?>
        <a class="item-detail__affiliate" href="<?= e($affiliateUrl) ?>" target="_blank" rel="noopener noreferrer">商品購入ページへ（FANZA）</a>
      <?php endif; ?>
    </div>

    <div class="item-detail__right">
      <?php if ($desc !== ''): ?>
        <div class="item-detail__desc"><?= nl2br(e($desc)) ?></div>
      <?php endif; ?>

      <ul class="item-detail__meta">
        <?php if (!empty($item['price_min_text'])): ?><li>価格: <?= e((string)$item['price_min_text']) ?></li><?php endif; ?>
        <?php if (!empty($item['release_date'])): ?><li>発売日: <?= e((string)$item['release_date']) ?></li><?php endif; ?>
        <?php if (!empty($item['review_average']) || !empty($item['review_count'])): ?>
          <li>レビュー: <?= e((string)($item['review_average'] ?? '')) ?> (<?= e((string)($item['review_count'] ?? '')) ?>)</li>
        <?php endif; ?>
      </ul>

      <?php foreach ($rels as $name => $vals): ?>
        <?php $text = implode(', ', array_filter(array_map('strval', $vals))); ?>
        <?php if (trim($text) !== ''): ?>
          <p class="item-detail__rel"><span class="item-detail__rel-name"><?= e((string)$name) ?>:</span> <?= e($text) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($sampleImages !== []): ?>
    <section class="item-detail__samples">
      <h3 class="item-detail__section-title">サンプル画像</h3>

      <div class="item-samples" data-sample-gallery="1">
        <div class="item-samples__viewer">
          <img id="sample-viewer" class="item-samples__viewer-img" src="<?= e((string)$sampleImages[0]) ?>" alt="サンプル画像（拡大）">
        </div>

        <div class="item-samples__thumbs">
          <?php foreach ($sampleImages as $index => $image): ?>
            <button type="button" class="item-samples__thumb" data-sample-src="<?= e($image) ?>" aria-label="サンプル画像 <?= e((string)($index + 1)) ?>">
              <img src="<?= e($image) ?>" alt="サンプル画像 <?= e((string)($index + 1)) ?>">
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($relatedItems !== []): ?>
    <section class="item-detail__related">
      <h3 class="item-detail__section-title">関連作品</h3>

      <div class="grid">
        <?php foreach ($relatedItems as $related): ?>
          <div class="card">
            <a href="<?= e(public_url('item.php?id=' . (int)$related['id'])) ?>"><?= e((string)($related['title'] ?? '')) ?></a><br>
            <?php if (!empty($related['image_small'])): ?>
              <img class="thumb" src="<?= e((string)$related['image_small']) ?>" alt="<?= e((string)($related['title'] ?? '')) ?>">
            <?php else: ?>
              画像なし
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</article>

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
  // sample movie modal
  const modal = document.getElementById('sample-movie-modal');
  const frame = document.getElementById('sample-movie-frame');
  const titleNode = document.getElementById('sample-movie-title');

  const openMovie = (url, title, movieWidth = 0) => {
    if (!modal || !frame || !titleNode) return;
    if (!url) return;
    const normalizedTitle = String(title || '').trim();
    titleNode.textContent = normalizedTitle !== '' ? normalizedTitle : 'サンプル動画';
    const normalizedWidth = Number.isFinite(movieWidth) ? Math.max(320, Math.min(900, Math.round(movieWidth))) : 900;
    modal.style.setProperty('--movie-modal-width', `${normalizedWidth}px`);
    frame.src = url;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  };

  const closeMovie = () => {
    if (!modal || !frame || !titleNode) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    frame.src = 'about:blank';
    modal.style.removeProperty('--movie-modal-width');
    titleNode.textContent = 'サンプル動画';
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.sample-movie-trigger');
    if (trigger) {
      event.preventDefault();
      openMovie(trigger.dataset.movieUrl || '', trigger.dataset.movieTitle || <?= json_encode((string)$item['title'], JSON_UNESCAPED_UNICODE) ?>);
      return;
    }

    if (event.target.closest('[data-movie-close="1"]')) {
      event.preventDefault();
      closeMovie();
    }

    const thumb = event.target.closest('.item-samples__thumb');
    if (thumb) {
      event.preventDefault();
      const src = thumb.dataset.sampleSrc || '';
      const viewer = document.getElementById('sample-viewer');
      if (viewer && src) {
        viewer.src = src;
      }
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
      closeMovie();
    }
  });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
