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

function parse_index_image_urls(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    $trimmed = trim($value);
    if ($trimmed !== '' && $trimmed[0] === '[') {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }
    }

    $parts = preg_split('/[\r\n,|\s]+/', $value);
    if (!is_array($parts)) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $parts), static fn(string $v): bool => $v !== ''));
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

function collect_image_urls_from_value(mixed $value, array &$urls): void
{
    if (is_string($value)) {
        $candidate = trim($value);
        if ($candidate !== '' && preg_match('/^https?:\/\//i', $candidate) === 1 && preg_match('/\.(?:jpe?g|png|webp)(?:\?|$)/i', $candidate) === 1) {
            $urls[] = $candidate;
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $child) {
        collect_image_urls_from_value($child, $urls);
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

    if (!$hasImageSample) {
        foreach (parse_index_image_urls((string)($item['image_list'] ?? '')) as $image) {
            if (trim((string)$image) !== '') {
                $hasImageSample = true;
                break;
            }
        }
    }

    return ['movie_url' => $firstMovieUrl, 'movie_urls' => $movieUrls, 'has_images' => $hasImageSample];
}

function pick_full_package_image(array $item): string
{
    $raw = decode_item_raw($item);
    $rawImageUrls = [];
    collect_image_urls_from_value($raw, $rawImageUrls);
    $rawImageUrls = array_values(array_unique($rawImageUrls));
    foreach ($rawImageUrls as $url) {
        if (preg_match('/(?:^|[\/_-])j[pn](?:[._-]|$)/i', $url) === 1) {
            return $url;
        }
    }
    $sampleImageUrl = $raw['sampleImageURL'] ?? null;
    if (is_array($sampleImageUrl)) {
        foreach (['sample_l', 'sample_s'] as $sampleKey) {
            $images = $sampleImageUrl[$sampleKey]['image'] ?? null;
            if (!is_array($images)) {
                continue;
            }
            foreach ($images as $image) {
                $candidate = trim((string)$image);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
    }

    foreach (parse_index_image_urls((string)($item['image_list'] ?? '')) as $image) {
        $candidate = trim((string)$image);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    foreach (['image_large', 'image_small'] as $key) {
        $candidate = trim((string)($item[$key] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function render_item_card(array $item, int $width = 180, ?array $taxonomy = null, bool $preferFullPackageImage = false): void
{
    $itemUrl = app_url('public/item.php?id=' . (int)$item['id']);
    $title = (string)($item['title'] ?? '');
    $sample = item_sample_state($item);
    $movieClass = $sample['movie_url'] !== '' ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    $imageClass = $sample['has_images'] ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    $sampleImagesUrl = public_url('sample_images.php?content_id=' . rawurlencode((string)($item['content_id'] ?? '')));
    $thumbUrl = trim((string)($item['image_small'] ?? ''));
    if ($preferFullPackageImage) {
        $fullPackageImage = pick_full_package_image($item);
        if ($fullPackageImage !== '') {
            $thumbUrl = $fullPackageImage;
        }
    }
    if ($thumbUrl === '') {
        $thumbUrl = trim((string)($item['image_large'] ?? ''));
    }
    ?>
    <article class="card rail-card rail-card--<?= (int)$width ?>" style="width:<?= (int)$width ?>px;min-width:<?= (int)$width ?>px;max-width:<?= (int)$width ?>px;">
      <?php if ($thumbUrl !== ''): ?>
        <img class="thumb" src="<?= e($thumbUrl) ?>" alt="<?= e($title) ?>" style="width:<?= (int)$width ?>px;max-width:<?= (int)$width ?>px;">
      <?php else: ?>
        <div class="rail-card__noimage" style="width:<?= (int)$width ?>px;height:<?= (int)$width ?>px;">画像なし</div>
      <?php endif; ?>
      <a class="rail-card__title" href="<?= e($itemUrl) ?>"><?= e($title) ?></a>
      <div class="sample-buttons">
        <button type="button" class="<?= e($movieClass) ?> sample-movie-trigger" <?= $sample['movie_url'] === '' ? 'disabled' : '' ?> data-movie-url="<?= e((string)$sample['movie_url']) ?>" data-movie-title="<?= e($title) ?>">サンプル動画</button>
        <button type="button" class="<?= e($imageClass) ?>" <?= !$sample['has_images'] ? 'disabled' : '' ?> onclick="<?= $sample['has_images'] ? "window.open('" . e($sampleImagesUrl) . "','_blank','noopener,noreferrer,width=760,height=540');" : 'return false;' ?>">サンプル画像</button>
        <button type="button" class="sample-button sample-button--enabled" onclick="window.location.href='<?= e($itemUrl) ?>';">詳細ページ</button>
      </div>
      <?php if ($taxonomy !== null): ?>
        <a class="rail-card__meta" href="<?= e((string)$taxonomy['url']) ?>"><?= e((string)$taxonomy['name']) ?></a>
      <?php endif; ?>
    </article>
    <?php
}

function safe_include_partial(string $filePath): void
{
    try {
        include $filePath;
    } catch (Throwable $e) {
        error_log('public/index.php include failed: ' . $filePath . ' ' . $e->getMessage());
    }
}

function safe_render_home_ad(string $positionKey): void
{
    try {
        if (!function_exists('get_ad_code') || !function_exists('render_ad')) {
            return;
        }

        if (get_ad_code($positionKey) === null) {
            return;
        }

        echo '<div class="site-ad">';
        render_ad($positionKey, 'home', 'pc');
        echo '</div>';
    } catch (Throwable $e) {
        error_log('public/index.php ad render failed: ' . $positionKey . ' ' . $e->getMessage());
    }
}

$title = 'トップ';
$itemCount = 0;

$latestTop = $latestBottom = $pickupTop = $pickupBottom = [];
$fallbackItems = [];
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
        $fallbackItems = array_slice($latestRows, 0, 12);

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
            $genreCandidates = $pdo->query('SELECT g.id,g.name,COUNT(ig.id) AS item_count FROM genres g INNER JOIN item_genres ig ON ig.genre_id = g.id GROUP BY g.id,g.name HAVING COUNT(ig.id) > 0 ORDER BY item_count DESC,g.id DESC LIMIT 120')->fetchAll();
            $genreCandidates = seeded_shuffle($genreCandidates, $seedBase + 20);
            foreach (array_slice($genreCandidates, 0, 3) as $index => $genre) {
                $stmt = $pdo->prepare(
                    'SELECT i.id,i.content_id,i.title,i.image_small,i.image_large,i.image_list,i.raw_json,i.affiliate_url,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476,i.release_date,i.updated_at
                     FROM items i
                     INNER JOIN item_genres ig ON ig.content_id = i.content_id
                     WHERE ig.genre_id = :id
                     ORDER BY i.release_date DESC, i.updated_at DESC, i.id DESC
                     LIMIT 120'
                );
                $stmt->execute([':id' => (int)$genre['id']]);
                $genreItems = pick_random_items($stmt->fetchAll(), $seedBase + 30 + $index, 15);
                $genreRows[] = ['id' => (int)$genre['id'], 'name' => (string)$genre['name'], 'items' => $genreItems];
            }
        }

        if (db_table_exists($pdo, 'series') && db_table_exists($pdo, 'item_series')) {
            $seriesCandidates = $pdo->query('SELECT s.id,s.name,COUNT(isr.id) AS item_count FROM series s INNER JOIN item_series isr ON isr.series_id = s.id GROUP BY s.id,s.name HAVING COUNT(isr.id) > 0 ORDER BY item_count DESC,s.id DESC LIMIT 120')->fetchAll();
            if ($seriesCandidates !== []) {
                $seriesCandidates = seeded_shuffle($seriesCandidates, $seedBase + 40);
                $picked = $seriesCandidates[0];
                $stmt = $pdo->prepare(
                    'SELECT i.id,i.content_id,i.title,i.image_small,i.image_large,i.image_list,i.raw_json,i.affiliate_url,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476,i.release_date,i.updated_at
                     FROM items i
                     INNER JOIN item_series isr ON isr.content_id = i.content_id
                     WHERE isr.series_id = :id
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
            $makerCandidates = $pdo->query('SELECT m.id,m.name,COUNT(im.id) AS item_count FROM makers m INNER JOIN item_makers im ON im.maker_id = m.id GROUP BY m.id,m.name HAVING COUNT(im.id) > 0 ORDER BY item_count DESC,m.id DESC LIMIT 120')->fetchAll();
            if ($makerCandidates !== []) {
                $makerCandidates = seeded_shuffle($makerCandidates, $seedBase + 50);
                $picked = $makerCandidates[0];
                $stmt = $pdo->prepare(
                    'SELECT i.id,i.content_id,i.title,i.image_small,i.image_large,i.image_list,i.raw_json,i.affiliate_url,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476,i.release_date,i.updated_at
                     FROM items i
                     INNER JOIN item_makers im ON im.content_id = i.content_id
                     WHERE im.maker_id = :id
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
                    'SELECT i.id,i.content_id,i.title,i.image_small,i.image_large,i.image_list,i.raw_json,i.affiliate_url,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476,i.release_date,i.updated_at
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
$hasHomeContent = $latestTop !== []
    || $latestBottom !== []
    || $pickupTop !== []
    || $pickupBottom !== []
    || $actresses !== []
    || $genreRows !== []
    || $seriesSection['items'] !== []
    || $makerSection['items'] !== []
    || $authorSection['items'] !== [];
?>

<?php if ($itemCount === 0): ?>
  <div class="card"><p>まだ商品データが同期されていません。管理画面のAPI設定から「同期実行（DB保存）」を行ってください。</p></div>
<?php elseif (!$hasHomeContent): ?>
  <div class="card">
    <h2>表示できる本文データがまだありません</h2>
    <p>商品データは存在しますが、トップページに表示するセクションを組み立てられませんでした。下の作品一覧から確認してください。</p>
    <p><a class="button button--primary" href="<?= e(public_url('items.php')) ?>">商品一覧を見る</a></p>
  </div>
  <?php if ($fallbackItems !== []): ?>
    <section class="rail-section">
      <h2>取得できた作品</h2>
      <div class="rail-row rail-row--180"><?php foreach ($fallbackItems as $item) { render_item_card($item, 180); } ?></div>
    </section>
  <?php endif; ?>
<?php else: ?>
  <section class="rail-section">
    <h2>新着作品</h2>
    <div class="rail-row rail-row--210 rail-row--no-scroll rail-row--top-shift"><?php foreach ($latestTop as $item) { render_item_card($item, 210); } ?></div>
    <div class="rail-row rail-row--200 rail-row--wide-thumb"><?php foreach ($latestBottom as $item) { render_item_card($item, 200, null, true); } ?></div>
  </section>

  <section class="rail-section">
    <h2>ピックアップ（人気順）</h2>
    <div class="rail-row rail-row--210 rail-row--no-scroll rail-row--top-shift"><?php foreach ($pickupTop as $item) { render_item_card($item, 210); } ?></div>
    <div class="rail-row rail-row--200 rail-row--wide-thumb"><?php foreach ($pickupBottom as $item) { render_item_card($item, 200, null, true); } ?></div>
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

  <?php if (!empty($seriesSection['items'])): ?>
  <section class="rail-section">
    <h2>シリーズ<?= $seriesSection['name'] !== '' ? '：' . e($seriesSection['name']) : '' ?></h2>
    <div class="rail-row rail-row--180">
      <?php foreach ($seriesSection['items'] as $item) { render_item_card($item, 180, ['name' => (string)$seriesSection['name'], 'url' => (string)$seriesSection['url']]); } ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($makerSection['items'])): ?>
  <section class="rail-section">
    <h2>メーカー<?= $makerSection['name'] !== '' ? '：' . e($makerSection['name']) : '' ?></h2>
    <div class="rail-row rail-row--180">
      <?php foreach ($makerSection['items'] as $item) { render_item_card($item, 180, ['name' => (string)$makerSection['name'], 'url' => (string)$makerSection['url']]); } ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($authorSection['items'])): ?>
  <section class="rail-section">
    <h2>作者<?= $authorSection['name'] !== '' ? '：' . e($authorSection['name']) : '' ?></h2>
    <div class="rail-row rail-row--180">
      <?php foreach ($authorSection['items'] as $item) { render_item_card($item, 180, ['name' => (string)$authorSection['name'], 'url' => (string)$authorSection['url']]); } ?>
    </div>
  </section>
  <?php endif; ?>
<?php endif; ?>


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

  const openMovie = (url, title) => {
    if (!url) return;
    const normalizedTitle = String(title || '').trim();
    titleNode.textContent = normalizedTitle !== '' ? normalizedTitle : 'サンプル動画';
    modal.style.setProperty('--movie-modal-width', '900px');
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
      openMovie(trigger.dataset.movieUrl || '', trigger.dataset.movieTitle || fallbackTitle);
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
