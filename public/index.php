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

function item_sample_state(array $item): array
{
    $raw = decode_item_raw($item);
    $movie = '';
    foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $column) {
        $candidate = trim((string)($item[$column] ?? ''));
        if ($candidate !== '') {
            $movie = $candidate;
            break;
        }
    }

    $rawMovie = $raw['sampleMovieURL'] ?? null;
    if ($movie === '' && is_array($rawMovie)) {
        foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
            $candidate = trim((string)($rawMovie[$movieKey] ?? ''));
            if ($candidate !== '') {
                $movie = $candidate;
                break;
            }
        }
    }

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

    return ['movie_url' => $movie, 'has_images' => $hasImageSample];
}

function render_item_card(array $item, int $width = 180, ?array $taxonomy = null): void
{
    $itemUrl = app_url('public/item.php?id=' . (int)$item['id']);
    $title = (string)($item['title'] ?? '');
    $sample = item_sample_state($item);
    $movieClass = $sample['movie_url'] !== '' ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    $imageClass = $sample['has_images'] ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    ?>
    <article class="card rail-card rail-card--<?= (int)$width ?>">
      <?php if (!empty($item['image_small'])): ?>
        <img class="thumb" src="<?= e((string)$item['image_small']) ?>" alt="<?= e($title) ?>">
      <?php else: ?>
        <div class="rail-card__noimage">画像なし</div>
      <?php endif; ?>
      <a class="rail-card__title" href="<?= e($itemUrl) ?>"><?= e($title) ?></a>
      <div class="sample-buttons">
        <button type="button" class="<?= e($movieClass) ?>" <?= $sample['movie_url'] === '' ? 'disabled' : '' ?> onclick="<?= $sample['movie_url'] !== '' ? "window.open('" . e((string)$sample['movie_url']) . "','_blank','noopener,noreferrer');" : 'return false;' ?>">サンプル動画</button>
        <button type="button" class="<?= e($imageClass) ?>" <?= !$sample['has_images'] ? 'disabled' : '' ?> onclick="<?= $sample['has_images'] ? "window.open('" . e(public_url('sample_images.php?content_id=' . rawurlencode((string)($item['content_id'] ?? '')))) . "','_blank','noopener,noreferrer,width=820,height=520');" : 'return false;' ?>">サンプル画像</button>
      </div>
      <?php if ($taxonomy !== null): ?>
        <a class="rail-card__meta" href="<?= e((string)$taxonomy['url']) ?>"><?= e((string)$taxonomy['name']) ?></a>
      <?php endif; ?>
    </article>
    <?php
}

$title = 'トップ';
$dbMessage = '';
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

        $latestRows = $pdo->query('SELECT id,content_id,title,image_small,raw_json,sample_movie_url_720,sample_movie_url_644,sample_movie_url_560,sample_movie_url_476,release_date,updated_at FROM items ORDER BY release_date DESC, updated_at DESC, id DESC LIMIT 20')->fetchAll();
        $latestTop = array_slice($latestRows, 0, 5);
        $latestBottom = array_slice($latestRows, 5, 15);

        $popularRows = $pdo->query('SELECT id,content_id,title,image_small,raw_json,sample_movie_url_720,sample_movie_url_644,sample_movie_url_560,sample_movie_url_476,view_count,release_date FROM items ORDER BY view_count DESC, release_date DESC, id DESC LIMIT 20')->fetchAll();
        $pickupTop = array_slice($popularRows, 0, 5);
        $pickupBottom = array_slice($popularRows, 5, 15);

        $actressCandidates = $pdo->query('SELECT id,name,image_small FROM actresses ORDER BY (CASE WHEN image_small IS NULL OR image_small = "" THEN 1 ELSE 0 END), id DESC LIMIT 200')->fetchAll();
        $actresses = pick_random_items($actressCandidates, $seedBase + 10, 15);

        $genreCandidates = $pdo->query('SELECT g.id,g.name,COUNT(ig.id) AS item_count FROM genres g INNER JOIN item_genres ig ON ig.genre_id = g.id GROUP BY g.id,g.name HAVING COUNT(ig.id) > 0 ORDER BY item_count DESC,g.id DESC LIMIT 120')->fetchAll();
        $genreCandidates = seeded_shuffle($genreCandidates, $seedBase + 20);
        foreach (array_slice($genreCandidates, 0, 3) as $index => $genre) {
            $stmt = $pdo->prepare('SELECT i.id,i.content_id,i.title,i.image_small,i.raw_json,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476 FROM items i INNER JOIN item_genres ig ON ig.content_id = i.content_id WHERE ig.genre_id = :id ORDER BY i.release_date DESC, i.id DESC LIMIT 120');
            $stmt->execute([':id' => (int)$genre['id']]);
            $genreItems = pick_random_items($stmt->fetchAll(), $seedBase + 30 + $index, 15);
            $genreRows[] = ['id' => (int)$genre['id'], 'name' => (string)$genre['name'], 'items' => $genreItems];
        }

        $seriesCandidates = $pdo->query('SELECT s.id,s.name,COUNT(isr.id) AS item_count FROM series s INNER JOIN item_series isr ON isr.series_id = s.id GROUP BY s.id,s.name HAVING COUNT(isr.id) > 0 ORDER BY item_count DESC,s.id DESC LIMIT 120')->fetchAll();
        if ($seriesCandidates !== []) {
            $seriesCandidates = seeded_shuffle($seriesCandidates, $seedBase + 40);
            $picked = $seriesCandidates[0];
            $stmt = $pdo->prepare('SELECT i.id,i.content_id,i.title,i.image_small,i.raw_json,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476 FROM items i INNER JOIN item_series isr ON isr.content_id = i.content_id WHERE isr.series_id = :id ORDER BY i.release_date DESC, i.id DESC LIMIT 120');
            $stmt->execute([':id' => (int)$picked['id']]);
            $seriesSection = ['name' => (string)$picked['name'], 'url' => app_url('public/series_one.php?id=' . (int)$picked['id']), 'items' => pick_random_items($stmt->fetchAll(), $seedBase + 41, 15)];
        }

        $makerCandidates = $pdo->query('SELECT m.id,m.name,COUNT(im.id) AS item_count FROM makers m INNER JOIN item_makers im ON im.maker_id = m.id GROUP BY m.id,m.name HAVING COUNT(im.id) > 0 ORDER BY item_count DESC,m.id DESC LIMIT 120')->fetchAll();
        if ($makerCandidates !== []) {
            $makerCandidates = seeded_shuffle($makerCandidates, $seedBase + 50);
            $picked = $makerCandidates[0];
            $stmt = $pdo->prepare('SELECT i.id,i.content_id,i.title,i.image_small,i.raw_json,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476 FROM items i INNER JOIN item_makers im ON im.content_id = i.content_id WHERE im.maker_id = :id ORDER BY i.release_date DESC, i.id DESC LIMIT 120');
            $stmt->execute([':id' => (int)$picked['id']]);
            $makerSection = ['name' => (string)$picked['name'], 'url' => app_url('public/maker.php?id=' . (int)$picked['id']), 'items' => pick_random_items($stmt->fetchAll(), $seedBase + 51, 15)];
        }

        $authorCandidates = $pdo->query('SELECT a.id,a.name,COUNT(ia.id) AS item_count FROM authors a INNER JOIN item_authors ia ON ia.dmm_id = a.dmm_id GROUP BY a.id,a.name HAVING COUNT(ia.id) > 0 ORDER BY item_count DESC,a.id DESC LIMIT 120')->fetchAll();
        if ($authorCandidates !== []) {
            $authorCandidates = seeded_shuffle($authorCandidates, $seedBase + 60);
            $picked = $authorCandidates[0];
            $stmt = $pdo->prepare('SELECT i.id,i.content_id,i.title,i.image_small,i.raw_json,i.sample_movie_url_720,i.sample_movie_url_644,i.sample_movie_url_560,i.sample_movie_url_476 FROM items i INNER JOIN item_authors ia ON ia.item_id = i.id INNER JOIN authors a ON a.dmm_id = ia.dmm_id WHERE a.id = :id ORDER BY i.release_date DESC, i.id DESC LIMIT 120');
            $stmt->execute([':id' => (int)$picked['id']]);
            $authorSection = ['name' => (string)$picked['name'], 'url' => app_url('public/author.php?id=' . (int)$picked['id']), 'items' => pick_random_items($stmt->fetchAll(), $seedBase + 61, 15)];
        }
    }
} catch (Throwable $e) {
    $dbMessage = 'DB接続に失敗しました（設定を確認してください）。管理画面のAPI設定から接続情報をご確認ください。';
}

require __DIR__ . '/partials/header.php';
?>
<div class="only-pc"><?php include __DIR__ . '/partials/rss_text_widget.php'; ?></div>
<?php render_ad('content_top', 'home', 'pc'); ?>

<?php if ($dbMessage !== ''): ?>
  <div class="card"><p><?= e($dbMessage) ?></p><p><a href="<?= e(app_url('admin/affiliate_api.php')) ?>">管理画面のAPI設定へ</a></p></div>
<?php elseif ($itemCount === 0): ?>
  <div class="card"><p>まだ商品データが同期されていません。管理画面のAPI設定から「同期実行（DB保存）」を行ってください。</p></div>
<?php else: ?>
  <section class="rail-section">
    <h2>新着作品</h2>
    <div class="rail-row rail-row--300"><?php foreach ($latestTop as $item) { render_item_card($item, 300); } ?></div>
    <div class="rail-row rail-row--180"><?php foreach ($latestBottom as $item) { render_item_card($item, 180); } ?></div>
  </section>

  <section class="rail-section">
    <h2>ピックアップ（人気順）</h2>
    <div class="rail-row rail-row--300"><?php foreach ($pickupTop as $item) { render_item_card($item, 300); } ?></div>
    <div class="rail-row rail-row--180"><?php foreach ($pickupBottom as $item) { render_item_card($item, 180); } ?></div>
  </section>

  <section class="rail-section">
    <h2>女優</h2>
    <div class="rail-row rail-row--180">
      <?php foreach ($actresses as $actress): ?>
        <article class="card rail-card rail-card--180">
          <?php if (!empty($actress['image_small'])): ?><img class="thumb" src="<?= e((string)$actress['image_small']) ?>" alt="<?= e((string)$actress['name']) ?>"><?php else: ?><div class="rail-card__noimage">画像なし</div><?php endif; ?>
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
<?php require __DIR__ . '/partials/footer.php'; ?>
