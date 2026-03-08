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
    $largeImages = [];
    $smallImages = [];
    $rawLargeImages = $sampleImageUrl['sample_l']['image'] ?? null;
    $rawSmallImages = $sampleImageUrl['sample_s']['image'] ?? null;

    if (is_array($rawLargeImages)) {
        foreach ($rawLargeImages as $image) {
            $url = trim((string)($image ?? ''));
            if ($url !== '') {
                $largeImages[] = $url;
            }
        }
    }

    if (is_array($rawSmallImages)) {
        foreach ($rawSmallImages as $image) {
            $url = trim((string)($image ?? ''));
            if ($url !== '') {
                $smallImages[] = $url;
            }
        }
    }

    if ($largeImages !== [] || $smallImages !== []) {
        $maxCount = max(count($largeImages), count($smallImages));
        for ($i = 0; $i < $maxCount; $i++) {
            $large = $largeImages[$i] ?? ($smallImages[$i] ?? '');
            $small = $smallImages[$i] ?? ($largeImages[$i] ?? '');
            if ($large !== '' || $small !== '') {
                $sampleImages[] = [
                    'large' => $large,
                    'small' => $small,
                ];
            }
        }
    }
}

$descriptionText = trim((string)($item['description'] ?? ''));
if ($descriptionText === '') {
    $descriptionText = trim((string)($item['iteminfo'] ?? ''));
}
if ($descriptionText === '') {
    $descriptionText = '商品説明は準備中です。';
}

$title = (string)$item['title'];
require __DIR__ . '/partials/header.php';
?>
<h2><?= e((string)$item['title']) ?></h2>
<article class="item-article">
  <?php if ($sampleMovieUrl !== ''): ?>
    <section class="item-article__movie">
      <iframe src="<?= e($sampleMovieUrl) ?>" allow="autoplay; fullscreen" referrerpolicy="no-referrer" loading="lazy" title="<?= e((string)$item['title']) ?> サンプル動画"></iframe>
    </section>
  <?php endif; ?>

  <section class="item-article__summary">
    <div class="item-article__left">
      <?php if (!empty($item['image_large'])): ?>
        <img class="item-article__package" src="<?= e((string)$item['image_large']) ?>" alt="<?= e((string)$item['title']) ?>">
      <?php else: ?>
        <div class="item-article__package item-article__package--empty">画像なし</div>
      <?php endif; ?>

      <?php if (!empty($item['affiliate_url'])): ?>
        <a class="item-article__affiliate" href="<?= e((string)$item['affiliate_url']) ?>" target="_blank" rel="noopener noreferrer">商品購入ページへ（FANZA）</a>
      <?php endif; ?>
    </div>

    <div class="item-article__description">
      <p class="item-article__description-text"><?= nl2br(e($descriptionText)) ?></p>
      <ul>
        <li>価格: <?= e((string)($item['price_min_text'] ?? '')) ?></li>
        <li>発売日: <?= e((string)($item['release_date'] ?? '')) ?></li>
        <li>レビュー: <?= e((string)($item['review_average'] ?? '')) ?> (<?= e((string)($item['review_count'] ?? '')) ?>)</li>
      </ul>
      <?php foreach ($rels as $name => $vals): ?>
        <?php $relatedText = implode(', ', array_filter(array_map('strval', $vals))); ?>
        <?php if ($relatedText !== ''): ?>
          <p><?= e((string)$name) ?>: <?= e($relatedText) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($sampleImages !== []): ?>
    <section class="item-article__samples">
      <?php foreach ($sampleImages as $sampleImage): ?>
        <button type="button" class="item-article__sample-button" data-sample-image="<?= e((string)$sampleImage['large']) ?>" aria-label="サンプル画像を拡大表示">
          <img class="item-article__sample-thumb" src="<?= e((string)$sampleImage['small']) ?>" alt="<?= e((string)$item['title']) ?> サンプル画像">
        </button>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</article>

<?php if ($relatedItems !== []): ?>
  <h3>関連作品</h3>
  <div class="rail-row rail-row--no-scroll">
    <?php foreach ($relatedItems as $related): ?>
      <article class="card rail-card rail-card--180">
        <?php if (!empty($related['image_small'])): ?>
          <a href="<?= e(public_url('item.php?id=' . (int)$related['id'])) ?>">
            <img class="thumb" src="<?= e((string)$related['image_small']) ?>" alt="<?= e((string)($related['title'] ?? '')) ?>">
          </a>
        <?php else: ?>
          <div class="rail-card__noimage">画像なし</div>
        <?php endif; ?>
        <a class="rail-card__title" href="<?= e(public_url('item.php?id=' . (int)$related['id'])) ?>"><?= e((string)($related['title'] ?? '')) ?></a>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div id="sample-image-modal" class="sample-image-modal" aria-hidden="true">
  <div class="sample-image-modal__overlay" data-image-close="1"></div>
  <div class="sample-image-modal__dialog" role="dialog" aria-modal="true" aria-label="サンプル画像プレビュー">
    <button type="button" class="sample-image-modal__close" data-image-close="1" aria-label="閉じる">×</button>
    <img id="sample-image-modal-img" class="sample-image-modal__img" src="" alt="サンプル画像拡大">
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
  if (!modal || !frame || !titleNode) return;

  const openMovie = (url, title, movieWidth = 0) => {
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
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
      closeMovie();
    }
  });

  const imageModal = document.getElementById('sample-image-modal');
  const imageNode = document.getElementById('sample-image-modal-img');
  if (!imageModal || !imageNode) return;

  const openImage = (url) => {
    if (!url) return;
    imageNode.src = url;
    imageModal.classList.add('is-open');
    imageModal.setAttribute('aria-hidden', 'false');
  };

  const closeImage = () => {
    imageModal.classList.remove('is-open');
    imageModal.setAttribute('aria-hidden', 'true');
    imageNode.src = '';
  };

  document.addEventListener('click', (event) => {
    const imageTrigger = event.target.closest('[data-sample-image]');
    if (imageTrigger) {
      event.preventDefault();
      openImage(imageTrigger.getAttribute('data-sample-image') || '');
      return;
    }

    if (event.target.closest('[data-image-close="1"]')) {
      event.preventDefault();
      closeImage();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && imageModal.classList.contains('is-open')) {
      closeImage();
    }
  });
})();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
