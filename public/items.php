<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

function items_page_query_all_safe(PDO $pdo, string $sql, array $params = []): array
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
        error_log('public/items.php query failed: ' . $e->getMessage());
        return [];
    }
}

function items_page_table_exists(PDO $pdo, string $table): bool
{
    if (!in_array($table, ['rss_items', 'rss_sources'], true)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable) {
        return false;
    }
}

function items_page_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!in_array($table, ['items', 'rss_sources'], true)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $stmt->execute([':column' => $column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return false;
    }
}

function items_page_product_source_where(PDO $pdo): string
{
    static $where = null;
    if ($where !== null) {
        return $where;
    }

    $parts = [];
    if (items_page_column_exists($pdo, 'items', 'item_source')) {
        $parts[] = 'items.item_source = "fanza_product"';
    }
    if (items_page_table_exists($pdo, 'rss_items') && items_page_table_exists($pdo, 'rss_sources') && items_page_column_exists($pdo, 'rss_sources', 'source_type')) {
        $parts[] = 'NOT EXISTS (SELECT 1 FROM rss_items ri INNER JOIN rss_sources rs ON rs.id = ri.source_id WHERE rs.source_type = "partner_link" AND (ri.title = items.title OR ri.url = items.url OR ri.url = items.affiliate_url))';
    }

    $where = $parts !== [] ? ' WHERE ' . implode(' AND ', $parts) : '';
    return $where;
}

function items_page_decode_item_raw(array $item): array
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

function items_page_normalize_movie_url(string $url): string
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

function items_page_parse_image_urls(?string $value): array
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

function items_page_collect_movie_urls_from_value(mixed $value, array &$urls): void
{
    if (is_string($value)) {
        $candidate = items_page_normalize_movie_url($value);
        if ($candidate !== '') {
            $urls[] = $candidate;
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach ($value as $child) {
        items_page_collect_movie_urls_from_value($child, $urls);
    }
}

function items_page_pick_sample_movie_urls_from_raw(array $raw): array
{
    $urls = [];
    foreach (['sampleMovieURL', 'sample_movie_url', 'sampleMovieUrl'] as $movieKeyName) {
        $rawMovie = $raw[$movieKeyName] ?? null;

        if (is_string($rawMovie)) {
            $candidate = items_page_normalize_movie_url($rawMovie);
            if ($candidate !== '') {
                $urls[] = $candidate;
            }
        }

        if (is_array($rawMovie)) {
            foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                $candidate = items_page_normalize_movie_url((string)($rawMovie[$movieKey] ?? ''));
                if ($candidate !== '') {
                    $urls[] = $candidate;
                }
            }

            items_page_collect_movie_urls_from_value($rawMovie, $urls);
        }
    }

    return array_values(array_unique(array_filter(array_map(static fn($u) => trim((string)$u), $urls))));
}

function items_page_item_sample_state(array $item): array
{
    $raw = items_page_decode_item_raw($item);
    $movieUrls = [];
    foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $column) {
        $candidate = trim((string)($item[$column] ?? ''));
        if ($candidate !== '') {
            $movieUrls[] = $candidate;
        }
    }

    $movieUrls = array_values(array_unique(array_merge($movieUrls, items_page_pick_sample_movie_urls_from_raw($raw))));
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
        foreach (items_page_parse_image_urls((string)($item['image_list'] ?? '')) as $image) {
            if (trim((string)$image) !== '') {
                $hasImageSample = true;
                break;
            }
        }
    }

    return ['movie_url' => $firstMovieUrl, 'movie_urls' => $movieUrls, 'has_images' => $hasImageSample];
}

function items_page_pick_full_package_image(array $item): string
{
    foreach (['image_large', 'image_list', 'image_small'] as $key) {
        if ($key === 'image_list') {
            foreach (items_page_parse_image_urls((string)($item['image_list'] ?? '')) as $image) {
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

function items_page_render_item_card(array $item, int $width = 200): void
{
    $itemUrl = app_url('public/item.php?id=' . (int)$item['id']);
    $title = (string)($item['title'] ?? '');
    $sample = items_page_item_sample_state($item);
    $movieClass = $sample['movie_url'] !== '' ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    $imageClass = $sample['has_images'] ? 'sample-button sample-button--enabled' : 'sample-button sample-button--disabled';
    $sampleImagesUrl = public_url('sample_images.php?content_id=' . rawurlencode((string)($item['content_id'] ?? '')));
    $thumbUrl = items_page_pick_full_package_image($item);
    ?>
    <article class="card rail-card rail-card--<?= (int)$width ?>">
      <?php if ($thumbUrl !== ''): ?>
        <img class="thumb" src="<?= e($thumbUrl) ?>" alt="<?= e($title) ?>">
      <?php else: ?>
        <div class="rail-card__noimage">画像なし</div>
      <?php endif; ?>
      <a class="rail-card__title" href="<?= e($itemUrl) ?>"><?= e($title) ?></a>
      <div class="sample-buttons">
        <button type="button" class="<?= e($movieClass) ?> sample-movie-trigger" <?= $sample['movie_url'] === '' ? 'disabled' : '' ?> data-movie-url="<?= e((string)$sample['movie_url']) ?>" data-movie-title="<?= e($title) ?>">サンプル動画</button>
        <button type="button" class="<?= e($imageClass) ?>" <?= !$sample['has_images'] ? 'disabled' : '' ?> onclick="<?= $sample['has_images'] ? "window.open('" . e($sampleImagesUrl) . "','_blank','noopener,noreferrer,width=760,height=540');" : 'return false;' ?>">サンプル画像</button>
        <button type="button" class="sample-button sample-button--enabled" onclick="window.location.href='<?= e($itemUrl) ?>';">詳細ページ</button>
      </div>
    </article>
    <?php
}

$page = max(1, (int)get('page', 1));
$per = 32;
$rows = [];
$pg = paginate(0, $page, $per);

try {
    $pdo = db();
    $whereSql = items_page_product_source_where($pdo);
    $total = (int)$pdo->query('SELECT COUNT(*) FROM items' . $whereSql)->fetchColumn();
    $pg = paginate($total, $page, $per);
    $rows = items_page_query_all_safe(
        $pdo,
        'SELECT * FROM items' . $whereSql . ' ORDER BY release_date DESC, updated_at DESC, id DESC LIMIT :limit OFFSET :offset',
        [':limit' => (int)$pg['perPage'], ':offset' => (int)$pg['offset']]
    );
} catch (Throwable $e) {
    error_log('public/items.php failed: ' . $e->getMessage());
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php if ($rows !== []): ?>
  <section class="rail-section">
    <h1>商品一覧</h1>
    <p>最新の作品を一覧でチェックできます。</p>
    <div class="rail-row rail-row--200 rail-row--wide-thumb rail-row--no-scroll items-page-grid">
      <?php foreach ($rows as $item) { items_page_render_item_card(is_array($item) ? $item : [], 200); } ?>
    </div>
    <?php pcf_render_pagination($pg, public_url('items.php')); ?>
  </section>
<?php else: ?>
  <div class="card"><p>商品データがまだ登録されていません。</p></div>
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
