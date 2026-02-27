<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

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

$rels = [];
foreach (['item_actresses' => 'actress_name', 'item_genres' => 'genre_name', 'item_labels' => 'label_name', 'item_campaigns' => 'campaign_name', 'item_directors' => 'director_name', 'item_makers' => 'maker_name', 'item_series' => 'series_name', 'item_authors' => 'author_name', 'item_actors' => 'actor_name'] as $t => $c) {
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

$sampleMovieUrl = '';
foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $movieColumn) {
    $candidate = trim((string)($item[$movieColumn] ?? ''));
    if ($candidate !== '') {
        $sampleMovieUrl = $candidate;
        break;
    }
}


$rawMovie = $raw['sampleMovieURL'] ?? null;
if ($sampleMovieUrl === '' && is_array($rawMovie)) {
    foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
        $candidate = trim((string)($rawMovie[$movieKey] ?? ''));
        if ($candidate !== '') {
            $sampleMovieUrl = $candidate;
            break;
        }
    }
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

$title = (string)$item['title'];
require __DIR__ . '/partials/header.php';
?>
<h2><?= e($item['title']) ?></h2>
<?php if ($item['image_large']): ?>
  <img src="<?= e($item['image_large']) ?>" style="max-width:320px" alt="<?= e($item['title']) ?>">
<?php else: ?>
  <p>画像なし</p>
<?php endif; ?>

<div style="margin: 16px 0; display: flex; gap: 8px; flex-wrap: wrap;">
  <?php if ($sampleMovieUrl !== ''): ?>
    <button type="button" onclick="window.open('<?= e($sampleMovieUrl) ?>', '_blank', 'noopener,noreferrer')">サンプル動画</button>
  <?php endif; ?>
  <?php if ($sampleImages !== []): ?>
    <button type="button" onclick="window.open('<?= e(public_url('sample_images.php?content_id=' . rawurlencode((string)$item['content_id']))) ?>', '_blank', 'noopener,noreferrer')">サンプル画像</button>
  <?php endif; ?>
</div>

<ul>
  <li>価格: <?= e($item['price_min_text'] ?? '') ?></li>
  <li>発売日: <?= e($item['release_date'] ?? '') ?></li>
  <li>レビュー: <?= e((string)$item['review_average']) ?> (<?= e((string)$item['review_count']) ?>)</li>
</ul>
<?php foreach ($rels as $name => $vals): ?>
  <p><?= e($name) ?>: <?= e(implode(', ', array_filter($vals))) ?></p>
<?php endforeach; ?>
<?php if ($item['affiliate_url']): ?>
  <p><a href="<?= e($item['affiliate_url']) ?>" target="_blank" rel="noopener noreferrer">FANZAで見る</a></p>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
