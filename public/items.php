<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

function dedupe_items_for_listing(array $items): array
{
    $seen = [];
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $contentId = strtolower(trim((string)($item['content_id'] ?? '')));
        $productId = strtolower(trim((string)($item['product_id'] ?? '')));
        $id = trim((string)($item['id'] ?? ''));
        $key = $contentId !== '' ? 'content_id:' . $contentId : ($productId !== '' ? 'product_id:' . $productId : ($id !== '' ? 'id:' . $id : ''));
        if ($key !== '' && isset($seen[$key])) {
            continue;
        }
        if ($key !== '') {
            $seen[$key] = true;
        }
        $result[] = $item;
    }
    return $result;
}




function items_page_find_first_string(mixed $value): string
{
    if (is_string($value)) {
        $v = trim($value);
        return $v;
    }
    if (!is_array($value)) {
        return '';
    }
    foreach ($value as $child) {
        $found = items_page_find_first_string($child);
        if ($found !== '') {
            return $found;
        }
    }
    return '';
}

function items_page_find_first_title(mixed $value): string
{
    if (!is_array($value)) {
        return '';
    }
    if (isset($value['title']) && is_string($value['title']) && trim($value['title']) !== '') {
        return trim($value['title']);
    }
    foreach ($value as $child) {
        $found = items_page_find_first_title($child);
        if ($found !== '') {
            return $found;
        }
    }
    return '';
}

function items_page_pick_image_url(array $item): string
{
    foreach (['image_large', 'image_small'] as $key) {
        $v = trim((string)($item[$key] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    $listRaw = trim((string)($item['image_list'] ?? ''));
    if ($listRaw !== '') {
        if ($listRaw[0] === '[') {
            $decoded = json_decode($listRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $url) {
                    $u = trim((string)$url);
                    if ($u !== '') return $u;
                }
            }
        }
        $parts = preg_split('/[
,|\s]+/', $listRaw);
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $u = trim((string)$part);
                if ($u !== '') return $u;
            }
        }
    }
    $raw = json_decode((string)($item['raw_json'] ?? ''), true);
    if (is_array($raw)) {
        foreach (['imageURL', 'packageImage', 'jacket'] as $k) {
            if (isset($raw[$k])) {
                $u = items_page_find_first_string($raw[$k]);
                if ($u !== '') {
                    return $u;
                }
            }
        }
    }
    return pcf_placeholder_data_uri('No Image');
}


function hydrate_items_page_row(array $item): array
{
    $title = trim((string)($item['title'] ?? ''));
    $hasImage = trim((string)($item['image_large'] ?? '')) !== '' || trim((string)($item['image_small'] ?? '')) !== '' || trim((string)($item['image_list'] ?? '')) !== '';
    $hasMovie = trim((string)($item['sample_movie_url_720'] ?? '')) !== '' || trim((string)($item['sample_movie_url_644'] ?? '')) !== '' || trim((string)($item['sample_movie_url_560'] ?? '')) !== '' || trim((string)($item['sample_movie_url_476'] ?? '')) !== '';
    $contentId = trim((string)($item['content_id'] ?? ''));
    if ($contentId === '' || ($title !== '' && $hasImage && $hasMovie)) {
        return $item;
    }
    try {
        $full = fetch_item_by_content_id($contentId);
        if (is_array($full)) {
            return array_merge($item, $full);
        }
    } catch (Throwable) {
    }
    return $item;
}

function render_items_page_card(array $item): void
{
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') {
        $raw = json_decode((string)($item['raw_json'] ?? ''), true);
        if (is_array($raw)) {
            $title = items_page_find_first_title($raw);
        }
    }
    if ($title === '') {
        $title = 'タイトル未設定';
    }
    $contentId = trim((string)($item['content_id'] ?? ''));
    $itemUrl = $contentId !== ''
        ? public_url('item.php?cid=' . rawurlencode($contentId))
        : public_url('item.php?id=' . (int)($item['id'] ?? 0));
    $sampleImageUrl = $contentId !== '' ? public_url('sample_images.php?content_id=' . rawurlencode($contentId)) : '';
    $sampleMovieUrl = '';
    foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $movieColumn) {
        $candidate = trim((string)($item[$movieColumn] ?? ''));
        if ($candidate !== '') {
            $sampleMovieUrl = $candidate;
            break;
        }
    }

    echo '<article class="pcf-listing-card">';
    echo '<a href="' . e($itemUrl) . '" class="pcf-listing-card__image-link">';
    echo '<img class="pcf-listing-card__image" src="' . e(items_page_pick_image_url($item)) . '" alt="' . e($title) . '" loading="lazy">';
    echo '</a>';
    echo '<a class="pcf-listing-card__title" href="' . e($itemUrl) . '">' . e($title) . '</a>';
    echo '<div class="pcf-listing-card__buttons">';
    if ($sampleMovieUrl !== '') {
        echo '<a class="pcf-listing-card__btn" href="' . e($sampleMovieUrl) . '" target="_blank" rel="noopener noreferrer">サンプル動画</a>';
    } else {
        echo '<span class="pcf-listing-card__btn is-disabled">サンプル動画</span>';
    }
    if ($sampleImageUrl !== '') {
        echo '<a class="pcf-listing-card__btn" href="' . e($sampleImageUrl) . '" target="_blank" rel="noopener noreferrer">サンプル画像</a>';
    } else {
        echo '<span class="pcf-listing-card__btn is-disabled">サンプル画像</span>';
    }
    echo '<a class="pcf-listing-card__btn" href="' . e($itemUrl) . '">詳細ページ</a>';
    echo '</div>';
    echo '</article>';
}

$page = max(1, (int)get('page', 1));
$per = 20;
$total = 0;
$rows = [];

try {
    $total = (int)db()->query("SELECT COUNT(*) FROM items")->fetchColumn();
} catch (Throwable) {
    $total = 0;
}

$pg = paginate($total, $page, (int)$per);

$orderSqlCandidates = [
    'created_at DESC, id DESC',
    'updated_at DESC, id DESC',
    'release_date DESC, id DESC',
    'id DESC',
];
foreach ($orderSqlCandidates as $orderSql) {
    try {
        $stmt = db()->prepare('SELECT * FROM items ORDER BY ' . $orderSql . ' LIMIT :l OFFSET :o');
        $stmt->bindValue(':l', (int)$pg['perPage'], PDO::PARAM_INT);
        $stmt->bindValue(':o', (int)$pg['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        $rows = dedupe_items_for_listing($rows);
        $rows = array_map(static fn($row) => is_array($row) ? hydrate_items_page_row($row) : [], $rows);
        break;
    } catch (Throwable) {
        $rows = [];
    }
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section class="pcf-grid pcf-grid--items">
    <?php foreach ($rows as $r): ?>
      <?php render_items_page_card(is_array($r) ? $r : []); ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
