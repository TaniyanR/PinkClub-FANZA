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
    if (is_string($decoded)) {
        $decodedAgain = json_decode($decoded, true);
        if (is_array($decodedAgain)) {
            return $decodedAgain;
        }
    }

    return is_array($decoded) ? $decoded : [];
}

function items_listing_column_exists(string $column): bool
{
    static $cache = [];
    if (isset($cache[$column])) {
        return $cache[$column];
    }

    try {
        $stmt = db()->prepare('SHOW COLUMNS FROM items LIKE :column');
        $stmt->execute([':column' => $column]);
        $cache[$column] = (bool)$stmt->fetch();
    } catch (Throwable) {
        $cache[$column] = false;
    }

    return $cache[$column];
}

function items_listing_order_sql(): string
{
    $order = [];
    foreach (['date_published', 'release_date', 'updated_at'] as $column) {
        if (items_listing_column_exists($column)) {
            $order[] = $column . ' DESC';
        }
    }
    $order[] = 'id DESC';

    return implode(', ', $order);
}

function items_listing_decode_nested(mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }

    $trimmed = trim($value);
    if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
        return $value;
    }

    $decoded = json_decode($trimmed, true);
    return is_array($decoded) ? $decoded : $value;
}

function items_listing_find_by_keys(mixed $value, array $keys): string
{
    $value = items_listing_decode_nested($value);
    if (!is_array($value)) {
        return '';
    }

    foreach ($keys as $key) {
        if (!array_key_exists($key, $value)) {
            continue;
        }
        $candidate = items_listing_first_text($value[$key]);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    foreach ($value as $child) {
        $candidate = items_listing_find_by_keys($child, $keys);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function items_listing_find_image_by_keys(mixed $value): string
{
    $value = items_listing_decode_nested($value);
    if (!is_array($value)) {
        return '';
    }

    foreach (['packageImage', 'imageURL', 'image_url', 'main_image_url', 'full_package_url', 'large', 'small', 'list'] as $key) {
        if (!array_key_exists($key, $value)) {
            continue;
        }
        $candidate = function_exists('pcf_first_image_from_mixed') ? pcf_first_image_from_mixed($value[$key]) : items_listing_first_text($value[$key]);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    foreach ($value as $child) {
        $candidate = items_listing_find_image_by_keys($child);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function items_listing_first_text(mixed $value): string
{
    if (is_string($value)) {
        $decoded = items_listing_decode_nested($value);
        if (is_array($decoded)) {
            return items_listing_first_text($decoded);
        }
        return trim($value);
    }
    if (is_numeric($value)) {
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
        items_listing_find_by_keys($raw, ['title', 'productTitle']),
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
        (string)($item['full_package_url'] ?? ''),
        (string)($item['main_image_url'] ?? ''),
        (string)($item['image_url'] ?? ''),
        (string)($item['package_image_large'] ?? ''),
        (string)($item['package_image_small'] ?? ''),
        (string)($item['image_large'] ?? ''),
        (string)($item['image_small'] ?? ''),
        (string)($raw['packageImage']['large'] ?? ''),
        (string)($raw['packageImage']['small'] ?? ''),
        (string)($raw['imageURL']['large'] ?? ''),
        (string)($raw['imageURL']['small'] ?? ''),
        function_exists('pcf_first_image_from_mixed') ? pcf_first_image_from_mixed($raw['imageURL']['list'] ?? null) : '',
        items_listing_find_image_by_keys($raw),
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

function items_listing_is_displayable(array $item): bool
{
    $title = items_listing_title($item);
    if ($title === '' || $title === 'タイトル未設定') {
        return false;
    }

    return items_listing_image($item) !== '';
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
$allRows = [];
$rows = [];

try {
    $stmt = db()->query('SELECT * FROM items ORDER BY ' . items_listing_order_sql());
    $allRows = $stmt ? ($stmt->fetchAll() ?: []) : [];
} catch (Throwable) {
    $allRows = [];
}

$displayRows = array_values(array_filter($allRows, static fn(array $item): bool => items_listing_is_displayable($item)));
$total = count($displayRows);
$pg = paginate($total, $page, $per);
$rows = array_slice($displayRows, (int)$pg['offset'], (int)$pg['perPage']);

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section style="display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:16px; align-items:stretch;">
    <?php foreach ($rows as $item): ?>
      <?php items_listing_render_card(is_array($item) ? $item : []); ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
