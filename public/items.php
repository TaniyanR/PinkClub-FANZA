<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';
require_once __DIR__ . '/../lib/repository.php';

function items_parse_image_urls(?string $value): array
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

function items_pick_full_package_image(array $item): string
{
    foreach (['image_large', 'image_list', 'image_small'] as $key) {
        if ($key === 'image_list') {
            foreach (items_parse_image_urls((string)($item['image_list'] ?? '')) as $image) {
                $candidate = trim((string)$image);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
            continue;
        }
        $candidate = trim((string)($item[$key] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }
    return '';
}

function items_normalize_image_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'data:image/')) {
        return $url;
    }
    return '';
}

function items_render_pickup_second_row_card(array $item): void
{
    $raw = [];
    $rawJson = (string)($item['raw_json'] ?? '');
    if ($rawJson !== '') {
        $decoded = json_decode($rawJson, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        }
    }
    $itemUrl = public_url('item.php?id=' . (int)($item['id'] ?? 0));
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') {
        $title = trim((string)($raw['title'] ?? $raw['iteminfo']['title'] ?? ''));
    }
    $sampleMovieUrl = '';
    foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $movieColumn) {
        $candidate = trim((string)($item[$movieColumn] ?? ''));
        if ($candidate !== '') {
            $sampleMovieUrl = $candidate;
            break;
        }
    }
    if ($sampleMovieUrl === '' && isset($raw['sampleMovieURL'])) {
        if (is_string($raw['sampleMovieURL'])) {
            $sampleMovieUrl = trim($raw['sampleMovieURL']);
        } elseif (is_array($raw['sampleMovieURL'])) {
            foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                $candidate = trim((string)($raw['sampleMovieURL'][$movieKey] ?? ''));
                if ($candidate !== '') {
                    $sampleMovieUrl = $candidate;
                    break;
                }
            }
        }
    }
    $sampleImagesUrl = public_url('sample_images.php?content_id=' . rawurlencode((string)($item['content_id'] ?? '')));
    $thumbUrl = items_pick_full_package_image($item);
    if ($thumbUrl === '' && isset($raw['imageURL']) && is_array($raw['imageURL'])) {
        foreach (['large', 'list', 'small'] as $imageKey) {
            $candidate = trim((string)($raw['imageURL'][$imageKey] ?? ''));
            if ($candidate !== '') {
                $thumbUrl = $candidate;
                break;
            }
        }
    }
    $hasImages = $thumbUrl !== '';
    ?>
    <article class="card rail-card rail-card--200" style="width:200px;min-width:200px;max-width:200px;">
      <?php if ($thumbUrl !== ''): ?>
        <img class="thumb" src="<?= e($thumbUrl) ?>" alt="<?= e($title) ?>" style="width:200px;max-width:200px;">
      <?php else: ?>
        <div class="rail-card__noimage" style="width:200px;height:200px;">No Image</div>
      <?php endif; ?>
      <a class="rail-card__title" href="<?= e($itemUrl) ?>"><?= e($title !== '' ? $title : 'タイトル未設定') ?></a>
      <div class="sample-buttons">
        <button type="button" class="<?= e($sampleMovieUrl !== '' ? 'sample-button sample-button--enabled sample-movie-trigger' : 'sample-button sample-button--disabled') ?>" <?= $sampleMovieUrl === '' ? 'disabled' : '' ?> data-movie-url="<?= e($sampleMovieUrl) ?>" data-movie-title="<?= e($title) ?>">サンプル動画</button>
        <button type="button" class="<?= e($hasImages ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled') ?>" <?= !$hasImages ? 'disabled' : '' ?> onclick="<?= $hasImages ? "window.open('" . e($sampleImagesUrl) . "','_blank','noopener,noreferrer,width=760,height=540');" : 'return false;' ?>">サンプル画像</button>
        <button type="button" class="sample-button sample-button--enabled" onclick="window.location.href='<?= e($itemUrl) ?>';">詳細ページ</button>
      </div>
    </article>
    <?php
}

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

        $score = 0;
        if (trim((string)($item['title'] ?? '')) !== '') {
            $score += 2;
        }
        if (trim((string)($item['image_small'] ?? '')) !== '' || trim((string)($item['image_large'] ?? '')) !== '' || trim((string)($item['image_list'] ?? '')) !== '') {
            $score += 2;
        }
        if (trim((string)($item['affiliate_url'] ?? '')) !== '') {
            $score += 1;
        }

        if ($key !== '' && isset($seen[$key])) {
            $index = (int)$seen[$key];
            $existing = $result[$index] ?? [];
            $existingScore = 0;
            if (trim((string)($existing['title'] ?? '')) !== '') {
                $existingScore += 2;
            }
            if (trim((string)($existing['image_small'] ?? '')) !== '' || trim((string)($existing['image_large'] ?? '')) !== '' || trim((string)($existing['image_list'] ?? '')) !== '') {
                $existingScore += 2;
            }
            if (trim((string)($existing['affiliate_url'] ?? '')) !== '') {
                $existingScore += 1;
            }
            if ($score > $existingScore) {
                $result[$index] = $item;
            }
            continue;
        }
        if ($key !== '') {
            $seen[$key] = count($result);
        }
        $result[] = $item;
    }
    return $result;
}

function is_displayable_item_for_listing(array $item): bool
{
    $title = trim((string)($item['title'] ?? ''));
    $raw = [];
    $rawJson = (string)($item['raw_json'] ?? '');
    if ($rawJson !== '') {
        $decoded = json_decode($rawJson, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        }
    }
    if ($title === '' || $title === 'タイトル未設定') {
        $title = trim((string)($raw['title'] ?? $raw['iteminfo']['title'] ?? ''));
        if ($title === '') {
            return false;
        }
    }

    foreach (['image_small', 'image_large', 'image_list'] as $key) {
        if (trim((string)($item[$key] ?? '')) !== '') {
            return true;
        }
    }

    if ($raw !== []) {
        foreach (['small', 'large', 'list'] as $imageKey) {
            if (trim((string)($raw['imageURL'][$imageKey] ?? '')) !== '') {
                return true;
            }
        }
    }

    return false;
}

$page = max(1, (int)get('page', 1));
$per = app_config()['pagination']['per_page'] ?? 24;
$total = 0;
$rows = [];

try {
    $total = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
} catch (Throwable) {
    $total = 0;
}

$pg = paginate($total, $page, (int)$per);

$orderSqlCandidates = [
    'view_count DESC, release_date DESC, id DESC',
    'view_count DESC, date_published DESC, id DESC',
    'view_count DESC, id DESC',
    'release_date DESC, id DESC',
    'date_published DESC, id DESC',
    'updated_at DESC, id DESC',
    'id DESC',
];
foreach ($orderSqlCandidates as $orderSql) {
    try {
        $chunkSize = (int)$pg['perPage'] + 1;
        $cursor = (int)$pg['offset'];
        $maxLoops = 6;
        $collected = [];

        for ($i = 0; $i < $maxLoops; $i++) {
            $stmt = db()->prepare('SELECT * FROM items ORDER BY ' . $orderSql . ' LIMIT :l OFFSET :o');
            $stmt->bindValue(':l', $chunkSize, PDO::PARAM_INT);
            $stmt->bindValue(':o', $cursor, PDO::PARAM_INT);
            $stmt->execute();
            $chunk = $stmt->fetchAll() ?: [];
            if ($chunk === []) {
                break;
            }

            $rawFetched = count($chunk);
            $chunk = array_values(array_filter($chunk, static fn(array $row): bool => is_displayable_item_for_listing($row)));
            $collected = dedupe_items_for_listing(array_merge($collected, $chunk));
            if (count($collected) > (int)$pg['perPage']) {
                break;
            }

            $cursor += $rawFetched;
            if ($rawFetched < $chunkSize) {
                break;
            }
        }

        $rows = array_slice($collected, 0, (int)$pg['perPage']);
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
  <section class="rail-row rail-row--200 rail-row--wide-thumb">
    <?php foreach ($rows as $r): ?>
      <?php
      $itemRow = is_array($r) ? $r : [];
      $contentId = trim((string)($itemRow['content_id'] ?? ''));
      if ($contentId !== '' && function_exists('fetch_item_by_content_id')) {
          try {
              $resolved = fetch_item_by_content_id($contentId);
              if (is_array($resolved)) {
                  $itemRow = array_merge($itemRow, $resolved);
              }
          } catch (Throwable) {
          }
      }
      pcf_render_item_card($itemRow, 200, true);
      ?>
    <?php endforeach; ?>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
