<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

function items_listing_raw(array $item): array
{
    $rawJson = (string)($item['raw_json'] ?? '');
    if ($rawJson === '') {
        return [];
    }

    $decoded = json_decode($rawJson, true);
    return is_array($decoded) ? $decoded : [];
}

function items_listing_first_text(mixed $value): string
{
    if (is_string($value) || is_numeric($value)) {
        return trim((string)$value);
    }
    if (!is_array($value)) {
        return '';
    }

    foreach (['name', 'value', 'title', 'productTitle'] as $key) {
        if (isset($value[$key])) {
            $candidate = items_listing_first_text($value[$key]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    foreach ($value as $child) {
        $candidate = items_listing_first_text($child);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function items_listing_title(array $item): string
{
    $raw = items_listing_raw($item);
    $candidates = [
        items_listing_first_text($item['title'] ?? ''),
        items_listing_first_text($raw['title'] ?? ''),
        items_listing_first_text($raw['name'] ?? ''),
        items_listing_first_text($raw['productTitle'] ?? ''),
        items_listing_first_text($raw['iteminfo']['title'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && $candidate !== 'タイトル未設定') {
            return $candidate;
        }
    }

    return 'タイトル未設定';
}

function items_listing_image(array $item): string
{
    $raw = items_listing_raw($item);
    $candidates = [
        (string)($item['image_large'] ?? ''),
        (string)($item['image_small'] ?? ''),
        (string)($raw['packageImage']['large'] ?? ''),
        (string)($raw['packageImage']['small'] ?? ''),
        (string)($raw['imageURL']['large'] ?? ''),
        (string)($raw['imageURL']['small'] ?? ''),
        function_exists('pcf_first_image_from_mixed') ? pcf_first_image_from_mixed($raw['imageURL']['list'] ?? null) : '',
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string)$candidate);
        if ($value !== '') {
            return $value;
        }
    }

    if (function_exists('pcf_parse_image_urls')) {
        foreach (pcf_parse_image_urls((string)($item['image_list'] ?? '')) as $image) {
            $value = trim((string)$image);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function items_listing_sample_movie(array $item): string
{
    foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $column) {
        $candidate = trim((string)($item[$column] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function items_listing_has_sample_images(array $item): bool
{
    $raw = items_listing_raw($item);
    $sampleImageURL = $raw['sampleImageURL'] ?? null;
    if (is_array($sampleImageURL)) {
        foreach (['sample_l', 'sample_s'] as $sampleKey) {
            $images = $sampleImageURL[$sampleKey]['image'] ?? null;
            if (!is_array($images)) {
                continue;
            }
            foreach ($images as $image) {
                if (trim((string)$image) !== '') {
                    return true;
                }
            }
        }
    }

    if (function_exists('pcf_parse_image_urls')) {
        foreach (pcf_parse_image_urls((string)($item['image_list'] ?? '')) as $image) {
            if (trim((string)$image) !== '') {
                return true;
            }
        }
    }

    return false;
}

function items_listing_render_card(array $item): void
{
    $raw = items_listing_raw($item);
    $title = items_listing_title($item);
    $imageUrl = items_listing_image($item);
    $contentId = trim((string)($item['content_id'] ?? ''));
    if ($contentId === '') {
        $contentId = trim((string)($raw['content_id'] ?? ''));
    }
    $itemId = (int)($item['id'] ?? 0);
    $itemUrl = $itemId > 0 ? public_url('item.php?id=' . $itemId) : public_url('item.php?cid=' . rawurlencode($contentId));
    $sampleMovieUrl = items_listing_sample_movie($item);
    $hasSampleImages = items_listing_has_sample_images($item);
    $sampleImagesUrl = public_url('sample_images.php?content_id=' . rawurlencode($contentId));

    echo '<article class="pcf-dm-card">';
    echo '<a class="pcf-dm-card__image-link" href="' . e($itemUrl) . '">';
    if ($imageUrl !== '') {
        echo '<img class="pcf-dm-card__image" src="' . e($imageUrl) . '" alt="' . e($title) . '" loading="lazy">';
    } else {
        echo '<div class="pcf-dm-card__no-image">No Image</div>';
    }
    echo '</a>';
    echo '<h3 class="pcf-dm-card__title"><a href="' . e($itemUrl) . '">' . e($title) . '</a></h3>';
    echo '<div class="pcf-dm-card__actions">';
    if ($sampleMovieUrl !== '') {
        echo '<button type="button" class="pcf-dm-card__button sample-movie-trigger" data-movie-url="' . e($sampleMovieUrl) . '" data-movie-title="' . e($title) . '">サンプル動画</button>';
    } else {
        echo '<span class="pcf-dm-card__button is-disabled">サンプル動画</span>';
    }
    if ($hasSampleImages && $contentId !== '') {
        echo '<button type="button" class="pcf-dm-card__button" onclick="window.open(\'' . e($sampleImagesUrl) . '\',\'_blank\',\'noopener,noreferrer,width=760,height=540\');">サンプル画像</button>';
    } else {
        echo '<span class="pcf-dm-card__button is-disabled">サンプル画像</span>';
    }
    echo '<a class="pcf-dm-card__button" href="' . e($itemUrl) . '">詳細ページ</a>';
    echo '</div>';
    echo '</article>';
}

$page = max(1, (int)get('page', 1));
$per = 32;
$total = 0;
$rows = [];

try {
    $total = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
} catch (Throwable) {
    $total = 0;
}

$pg = paginate($total, $page, $per);

try {
    $stmt = db()->prepare('SELECT * FROM items ORDER BY release_date DESC, id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', (int)$pg['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$pg['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];
} catch (Throwable) {
    $rows = [];
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section style="display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:16px; align-items:stretch; direction:rtl;">
    <?php foreach ($rows as $item): ?>
      <div style="direction:ltr;">
        <?php items_listing_render_card(is_array($item) ? $item : []); ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
