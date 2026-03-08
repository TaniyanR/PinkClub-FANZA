<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function seeded_shuffle(array $rows, int $seed): array
{
    $count = count($rows);
    if ($count <= 1) {
        return $rows;
    }

    mt_srand($seed);
    for ($i = $count - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];
    }
    return $rows;
}

function pick_random_items(array $rows, int $seed, int $limit = 15): array
{
    $rows = seeded_shuffle($rows, $seed);
    return array_slice($rows, 0, $limit);
}

function decode_item_raw(array $item): array
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

function normalize_movie_url(string $url): string
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

function collect_movie_urls_from_value(mixed $value, array &$urls): void
{
    if (is_string($value)) {
        $candidate = normalize_movie_url($value);
        if ($candidate !== '') {
            $urls[] = $candidate;
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $child) {
        collect_movie_urls_from_value($child, $urls);
    }
}

function pick_sample_movie_urls_from_raw(array $raw): array
{
    $urls = [];
    foreach (['sampleMovieURL', 'sample_movie_url', 'sampleMovieUrl'] as $movieKeyName) {
        $rawMovie = $raw[$movieKeyName] ?? null;

        if (is_string($rawMovie)) {
            $candidate = normalize_movie_url($rawMovie);
            if ($candidate !== '') {
                $urls[] = $candidate;
            }
        }

        if (is_array($rawMovie)) {
            foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                $candidate = normalize_movie_url((string)($rawMovie[$movieKey] ?? ''));
                if ($candidate !== '') {
                    $urls[] = $candidate;
                }
            }

            collect_movie_urls_from_value($rawMovie, $urls);
        }
    }

    return array_values(array_unique(array_filter(array_map(static fn($u) => trim((string)$u), $urls))));
}

function query_all_safe(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;
            if (is_int($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($param, (string)$value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('public/index.php query failed: ' . $e->getMessage());
        return [];
    }
}

function fetch_items_with_order_fallback(PDO $pdo, array $orderByCandidates, int $limit): array
{
    $limit = max(1, min(300, $limit));

    foreach ($orderByCandidates as $orderBy) {
        $rows = query_all_safe($pdo, 'SELECT * FROM items ORDER BY ' . $orderBy . ' LIMIT ' . $limit);
        if ($rows !== []) {
            return $rows;
        }
    }

    return [];
}

function item_sample_state(array $item): array
{
    $raw = decode_item_raw($item);
    $movieUrls = [];
    foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $column) {
        $candidate = trim((string)($item[$column] ?? ''));
        if ($candidate !== '') {
            $movieUrls[] = $candidate;
        }
    }

    $movieUrls = array_values(array_unique(array_merge($movieUrls, pick_sample_movie_urls_from_raw($raw))));
    $firstMovieUrl = $movieUrls[0] ?? '';

    $hasImageSample = false;
    $sampleImageUrl = $raw['sampleImageURL'] ?? null;
    if (is_array($sampleImageUrl)) {
        foreach (['sample_l', 'sample_s'] as $sampleKey) {
            $images = $sampleImageUrl[$sampleKey]['image'] ?? null;
            if (is_array($images)) {
                foreach ($images as $image) {
                    if (trim((string)$image) !== '') {
                        $hasImageSample = true;
                        break 2;
                    }
                }
            }
        }
    }

    return ['movie_url' => $firstMovieUrl, 'movie_urls' => $movieUrls, 'has_images' => $hasImageSample];
}

function render_item_card(array $item, int $width = 180, ?array $taxonomy = null): void
{
    $itemUrl = app_url('public/item.php?id=' . (int)$item['id']);
    $title = (string)($item['title'] ?? '');
    $sample = item_sample_state($item);
    $movieClass = $sample['movie_url'] !== '' ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    $imageClass = $sample['has_images'] ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    $affiliateUrl = trim((string)($item['affiliate_url'] ?? ''));
    $affiliateClass = $affiliateUrl !== '' ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    $sampleImagesUrl = public_url('sample_images.php?content_id=' . rawurlencode((string)($item['content_id'] ?? '')));
    ?>
    <article class="card rail-card rail-card--<?= (int)$width ?>" style="width:<?= (int)$width ?>px;min-width:<?= (int)$width ?>px;max-width:<?= (int)$width ?>px;">
      <?php if (!empty($item['image_small'])): ?>
        <img class="thumb" src="<?= e((string)$item['image_small']) ?>" alt="<?= e($title) ?>" style="width:<?= (int)$width ?>px;max-width:<?= (int)$width ?>px;">
      <?php else: ?>
        <div class="rail-card__noimage" style="width:<?= (int)$width ?>px;height:<?= (int)$width ?>px;">画像なし</div>
      <?php endif; ?>
      <a class="rail-card__title" href="<?= e($itemUrl) ?>"><?= e($title) ?></a>
      <div class="sample-buttons">
        <button type="button" class="<?= e($movieClass) ?> sample-movie-trigger" <?= $sample['movie_url'] === '' ? 'disabled' : '' ?> data-movie-url="<?= e((string)$sample['movie_url']) ?>" data-movie-title="<?= e($title) ?>">サンプル動画</button>
        <button type="button" class="<?= e($imageClass) ?>" <?= !$sample['has_images'] ? 'disabled' : '' ?> onclick="<?= $sample['has_images'] ? "window.open('" . e($sampleImagesUrl) . "','_blank','noopener,noreferrer,width=760,height=540');" : 'return false;' ?>">サンプル画像</button>
        <button type="button" class="sample-button sample-button--enabled" onclick="window.location.href='<?= e($itemUrl) ?>';">詳細ページ</button>
        <button type="button" class="<?= e($affiliateClass) ?>" <?= $affiliateUrl === '' ? 'disabled' : '' ?> onclick="<?= $affiliateUrl !== '' ? "window.open('" . e($affiliateUrl) . "','_blank','noopener,noreferrer');" : 'return false;' ?>">購入はコチラ</button>
      </div>
      <?php if ($taxonomy !== null): ?>
        <a class="rail-card__meta" href="<?= e((string)$taxonomy['url']) ?>"><?= e((string)$taxonomy['name']) ?></a>
      <?php endif; ?>
    </article>
    <?php
}

$title = 'トップ';
$itemCount = 0;

$latestTop = $latestBottom = $pickupTop = $pickupBottom = [];
$actresses = [];
$genreRows = [];
$seriesSection = ['name' => '', 'url' => '', 'items' => []];
$makerSection = ['name' => '', 'url' => '', 'items' => []];
$authorSection = ['name' => '', 'url' => '', 'items' => []];

try {
    $pdo = db();
    $itemCount = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

    if ($itemCount > 0) {
        $seedBase = intdiv(time(), 1800);

        $latestRows = fetch_items_with_order_fallback($pdo, [
            'release_date DESC, updated_at DESC, id DESC',
            'date_published DESC, updated_at DESC, id DESC',
            'updated_at DESC, id DESC',
            'id DESC',
        ], 20);
        $latestTop = array_slice($latestRows, 0, 5);
        $latestBottom = array_slice($latestRows, 5, 15);

        $popularRows = fetch_items_with_order_fallback($pdo, [
            'view_count DESC, release_date DESC, id DESC',
            'view_count DESC, date_published DESC, id DESC',
            'view_count DESC, id DESC',
            'id DESC',
        ], 20);
        $pickupTop = array_slice($popularRows, 0, 5);
        $pickupBottom = array_slice($popularRows, 5, 15);

        if (db_table_exists($pdo, 'actresses')) {
            $actressCandidates = $pdo->query('SELECT id,name,image_small FROM actresses ORDER BY (CASE WHEN image_small IS NULL OR image_small = "" THEN 1 ELSE 0 END), id DESC LIMIT 200')->fetchAll();
            $actresses = pick_random_items($actressCandidates, $seedBase + 10, 15);
        }

        if (db_table_exists($pdo, 'genres') && db_table_exists($pdo, 'item_genres')) {
            $genreCandidates = $pdo->query('SELECT g.id,g.name,COUNT(ig.id) AS item_count FROM genres g INNER JOIN item_genres ig ON ((ig.dmm_id IS NOT NULL AND ig.dmm_id != \'\' AND ig.dmm_id = g.dmm_id) OR ig.genre_name = g.name) GROUP BY g.id,g.name HAVING COUNT(ig.id) > 0 ORDER BY item_count DESC,g.id DESC LIMIT 120')->fetchAll();
            $genreCandidates = seeded_shuffle($genreCandidates, $seedBase + 20);
            foreach (array_slice($genreCandidates, 0, 3) as $index => $genre) {
                $stmt = $pdo->prepare(
                    'SELECT i.id,i.content_id,i.title,i.image_small,i.raw_json,i.affiliate_url,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476,i.release_date,i.updated_at
                     FROM items i
                     INNER JOIN item_genres ig ON ig.item_id = i.id
                     INNER JOIN genres g ON ((ig.dmm_id IS NOT NULL AND ig.dmm_id != \'\' AND g.dmm_id = ig.dmm_id) OR ig.genre_name = g.name)
                     WHERE g.id = :id
                     ORDER BY i.release_date DESC, i.updated_at DESC, i.id DESC
                     LIMIT 120'
                );
                $stmt->execute([':id' => (int)$genre['id']]);
                $genreItems = pick_random_items($stmt->fetchAll(), $seedBase + 30 + $index, 15);
                $genreRows[] = ['id' => (int)$genre['id'], 'name' => (string)$genre['name'], 'items' => $genreItems];
            }
        }

        if (db_table_exists($pdo, 'series_master') && db_table_exists($pdo, 'item_series')) {
            $seriesCandidates = $pdo->query('SELECT s.id,s.name,COUNT(isr.id) AS item_count FROM series_master s INNER JOIN item_series isr ON ((isr.dmm_id IS NOT NULL AND isr.dmm_id != \'\' AND isr.dmm_id = s.dmm_id) OR isr.series_name = s.name) GROUP BY s.id,s.name HAVING COUNT(isr.id) > 0 ORDER BY item_count DESC,s.id DESC LIMIT 120')->fetchAll();
            if ($seriesCandidates !== []) {
                $seriesCandidates = seeded_shuffle($seriesCandidates, $seedBase + 40);
                $picked = $seriesCandidates[0];
                $stmt = $pdo->prepare(
                    'SELECT i.id,i.content_id,i.title,i.image_small,i.raw_json,i.affiliate_url,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476,i.release_date,i.updated_at
                     FROM items i
                     INNER JOIN item_series isr ON isr.item_id = i.id
                     INNER JOIN series_master s ON ((isr.dmm_id IS NOT NULL AND isr.dmm_id != \'\' AND s.dmm_id = isr.dmm_id) OR isr.series_name = s.name)
                     WHERE s.id = :id
                     ORDER BY i.release_date DESC, i.updated_at DESC, i.id DESC
                     LIMIT 120'
                );
                $stmt->execute([':id' => (int)$picked['id']]);
                $seriesSection = [
                    'name' => (string)$picked['name'],
                    'url' => app_url('public/series_one.php?id=' . (int)$picked['id']),
                    'items' => pick_random_items($stmt->fetchAll(), $seedBase + 41, 15),
                ];
            }
        }

        if (db_table_exists($pdo, 'makers') && db_table_exists($pdo, 'item_makers')) {
            $makerCandidates = $pdo->query('SELECT m.id,m.name,COUNT(im.id) AS item_count FROM makers m INNER JOIN item_makers im ON ((im.dmm_id IS NOT NULL AND im.dmm_id != \'\' AND im.dmm_id = m.dmm_id) OR im.maker_name = m.name) GROUP BY m.id,m.name HAVING COUNT(im.id) > 0 ORDER BY item_count DESC,m.id DESC LIMIT 120')->fetchAll();
            if ($makerCandidates !== []) {
                $makerCandidates = seeded_shuffle($makerCandidates, $seedBase + 50);
                $picked = $makerCandidates[0];
                $stmt = $pdo->prepare(
                    'SELECT i.id,i.content_id,i.title,i.image_small,i.raw_json,i.affiliate_url,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476,i.release_date,i.updated_at
                     FROM items i
                     INNER JOIN item_makers im ON im.item_id = i.id
                     INNER JOIN makers m ON ((im.dmm_id IS NOT NULL AND im.dmm_id != \'\' AND m.dmm_id = im.dmm_id) OR im.maker_name = m.name)
                     WHERE m.id = :id
                     ORDER BY i.release_date DESC, i.updated_at DESC, i.id DESC
                     LIMIT 120'
                );
                $stmt->execute([':id' => (int)$picked['id']]);
                $makerSection = [
                    'name' => (string)$picked['name'],
                    'url' => app_url('public/maker.php?id=' . (int)$picked['id']),
                    'items' => pick_random_items($stmt->fetchAll(), $seedBase + 51, 15),
                ];
            }
        }

        if (db_table_exists($pdo, 'authors') && db_table_exists($pdo, 'item_authors')) {
            $authorCandidates = $pdo->query('SELECT a.id,a.name,COUNT(ia.id) AS item_count FROM authors a INNER JOIN item_authors ia ON ia.dmm_id = a.dmm_id GROUP BY a.id,a.name HAVING COUNT(ia.id) > 0 ORDER BY item_count DESC,a.id DESC LIMIT 120')->fetchAll();
            if ($authorCandidates !== []) {
                $authorCandidates = seeded_shuffle($authorCandidates, $seedBase + 60);
                $picked = $authorCandidates[0];
                $stmt = $pdo->prepare(
                    'SELECT i.id,i.content_id,i.title,i.image_small,i.raw_json,i.affiliate_url,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476,i.release_date,i.updated_at
                     FROM items i
                     INNER JOIN item_authors ia ON ia.item_id = i.id
                     INNER JOIN authors a ON a.dmm_id = ia.dmm_id
                     WHERE a.id = :id
                     ORDER BY i.release_date DESC, i.updated_at DESC, i.id DESC
                     LIMIT 120'
                );
                $stmt->execute([':id' => (int)$picked['id']]);
                $authorSection = [
                    'name' => (string)$picked['name'],
                    'url' => app_url('public/author.php?id=' . (int)$picked['id']),
                    'items' => pick_random_items($stmt->fetchAll(), $seedBase + 61, 15),
                ];
            }
        }
    }
} catch (Throwable $e) {
    error_log('public/index.php load failed: ' . $e->getMessage());
}

require __DIR__ . '/partials/header.php';
?>
<div class="only-pc"><?php include __DIR__ . '/partials/rss_text_widget.php'; ?></div>
<?php render_ad('content_top', 'home', 'pc'); ?>

<?php if ($itemCount === 0): ?>
  <div class="card"><p>まだ商品データが同期されていません。管理画面のAPI設定から「同期実行（DB保存）」を行ってください。</p></div>
<?php else: ?>
  <section class="rail-section">
    <h2>新着作品</h2>
    <div class="rail-row rail-row--220 rail-row--no-scroll"><?php foreach ($latestTop as $item) { render_item_card($item, 220); } ?></div>
    <div class="rail-row rail-row--200"><?php foreach ($latestBottom as $item) { render_item_card($item, 200); } ?></div>
  </section>

  <section class="rail-section">
    <h2>ピックアップ（人気順）</h2>
    <div class="rail-row rail-row--220 rail-row--no-scroll"><?php foreach ($pickupTop as $item) { render_item_card($item, 220); } ?></div>
    <div class="rail-row rail-row--200"><?php foreach ($pickupBottom as $item) { render_item_card($item, 200); } ?></div>
  </section>

  <section class="rail-section">
    <h2>女優</h2>
    <div class="rail-row rail-row--180">
      <?php foreach ($actresses as $actress): ?>
        <article class="card rail-card rail-card--180">
          <?php if (!empty($actress['image_small'])): ?><img class="thumb" src="<?= e((string)$actress['image_small']) ?>" alt="<?= e((string)$actress['name']) ?>"><?php else: ?><div class="rail-card__noimage" style="width:180px;height:180px;">画像なし</div><?php endif; ?>
          <a class="rail-card__title" href="<?= e(app_url('public/actress.php?id=' . (int)$actress['id'])) ?>"><?= e((string)$actress['name']) ?></a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="rail-section">
    <h2>ジャンル</h2>
    <?php foreach ($genreRows as $genre): ?>
      <h3><a href="<?= e(app_url('public/genre.php?id=' . (int)$genre['id'])) ?>"><?= e((string)$genre['name']) ?></a></h3>
      <div class="rail-row rail-row--180">
        <?php foreach ($genre['items'] as $item) { render_item_card($item, 180, ['name' => (string)$genre['name'], 'url' => app_url('public/genre.php?id=' . (int)$genre['id'])]); } ?>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="rail-section">
    <h2>シリーズ<?= $seriesSection['name'] !== '' ? '：' . e($seriesSection['name']) : '' ?></h2>
    <div class="rail-row rail-row--180">
      <?php foreach ($seriesSection['items'] as $item) { render_item_card($item, 180, ['name' => (string)$seriesSection['name'], 'url' => (string)$seriesSection['url']]); } ?>
    </div>
  </section>

  <section class="rail-section">
    <h2>メーカー<?= $makerSection['name'] !== '' ? '：' . e($makerSection['name']) : '' ?></h2>
    <div class="rail-row rail-row--180">
      <?php foreach ($makerSection['items'] as $item) { render_item_card($item, 180, ['name' => (string)$makerSection['name'], 'url' => (string)$makerSection['url']]); } ?>
    </div>
  </section>

  <section class="rail-section">
    <h2>作者<?= $authorSection['name'] !== '' ? '：' . e($authorSection['name']) : '' ?></h2>
    <div class="rail-row rail-row--180">
      <?php foreach ($authorSection['items'] as $item) { render_item_card($item, 180, ['name' => (string)$authorSection['name'], 'url' => (string)$authorSection['url']]); } ?>
    </div>
  </section>
<?php endif; ?>

<?php render_ad('content_bottom', 'home', 'pc'); ?>
<div class="only-pc"><?php include __DIR__ . '/partials/rss_text_widget.php'; ?></div>

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
    if (trigger && !trigger.disabled) {
      event.preventDefault();
      const card = trigger.closest('.rail-card');
      const fallbackTitle = card ? (card.querySelector('.rail-card__title')?.textContent || '') : '';
      const mediaNode = card ? card.querySelector('.thumb, .rail-card__noimage') : null;
      const mediaWidth = mediaNode ? mediaNode.getBoundingClientRect().width : 0;
      openMovie(trigger.dataset.movieUrl || '', trigger.dataset.movieTitle || fallbackTitle, mediaWidth);
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
})();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
