<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/repository.php';

function item_decode_raw(array $item): array
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

function item_collect_movie_urls(mixed $value, array &$urls): void
{
    if (is_string($value)) {
        $candidate = trim($value);
        if ($candidate !== '' && (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://'))) {
            $urls[] = $candidate;
        }
        return;
    }
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $child) {
        item_collect_movie_urls($child, $urls);
    }
}

function item_pick_sample_movie_url(array $item, array $raw): string
{
    foreach (['sample_movie_url_720', 'sample_movie_url_644', 'sample_movie_url_560', 'sample_movie_url_476'] as $movieColumn) {
        $candidate = trim((string)($item[$movieColumn] ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }
    }

    foreach (['sampleMovieURL', 'sample_movie_url', 'sampleMovieUrl'] as $movieKeyName) {
        $rawMovie = $raw[$movieKeyName] ?? null;
        if (is_string($rawMovie) && trim($rawMovie) !== '') {
            return trim($rawMovie);
        }
        if (is_array($rawMovie)) {
            foreach (['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'] as $movieKey) {
                $candidate = trim((string)($rawMovie[$movieKey] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
            $urls = [];
            item_collect_movie_urls($rawMovie, $urls);
            if ($urls !== []) {
                return $urls[0];
            }
        }
    }

    return '';
}

function item_sample_images(array $raw): array
{
    $sampleImages = [];
    $sampleImageUrl = $raw['sampleImageURL'] ?? null;
    if (is_array($sampleImageUrl)) {
        foreach (['sample_l', 'sample_s'] as $sampleKey) {
            $images = $sampleImageUrl[$sampleKey]['image'] ?? null;
            if (is_array($images)) {
                foreach ($images as $image) {
                    $url = trim((string)$image);
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

    return array_values(array_unique($sampleImages));
}


function item_names_from_iteminfo(array $raw, string $key): string
{
    $info = $raw['iteminfo'][$key] ?? [];
    if (!is_array($info)) {
        return '-';
    }
    if (isset($info['name'])) {
        $info = [$info];
    }
    $names = [];
    foreach ($info as $row) {
        if (is_array($row) && trim((string)($row['name'] ?? '')) !== '') {
            $names[] = trim((string)$row['name']);
        }
    }
    return $names !== [] ? implode(' / ', array_unique($names)) : '-';
}

function item_affiliate_link(array $item): string
{
    $affiliate = trim((string)($item['affiliate_url'] ?? ''));
    if ($affiliate !== '') {
        return $affiliate;
    }
    return trim((string)($item['url'] ?? ''));
}

function item_render_action_link(string $label, string $url, string $enabledClass = 'sample-button--enabled'): string
{
    if ($url === '') {
        return '<button type="button" class="sample-button sample-button--disabled" disabled>' . e($label) . '</button>';
    }

    return '<a class="sample-button ' . e($enabledClass) . '" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($label) . '</a>';
}

function item_fetch_related_sections(PDO $pdo, array $item): array
{
    $sections = [];
    $contentId = (string)($item['content_id'] ?? '');

    if ($contentId === '') {
        return $sections;
    }

    $map = [
        '同ジャンル' => ['table' => 'item_genres', 'id' => 'genre_id', 'master' => 'genres', 'name' => 'name'],
        '同メーカー' => ['table' => 'item_makers', 'id' => 'maker_id', 'master' => 'makers', 'name' => 'name'],
        '同女優' => ['table' => 'item_actresses', 'id' => 'actress_id', 'master' => 'actresses', 'name' => 'name'],
    ];

    foreach ($map as $label => $def) {
        if (!db_table_exists($pdo, $def['table']) || !db_table_exists($pdo, $def['master'])) {
            continue;
        }

        $taxonomyStmt = $pdo->prepare('SELECT DISTINCT ' . $def['id'] . ' AS taxonomy_id FROM ' . $def['table'] . ' WHERE content_id = :content_id LIMIT 3');
        $taxonomyStmt->execute([':content_id' => $contentId]);
        $taxonomyIds = array_filter(array_map('intval', $taxonomyStmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        if ($taxonomyIds === []) {
            continue;
        }

        $relatedItems = [];
        foreach ($taxonomyIds as $taxonomyId) {
            $sql = 'SELECT i.* FROM items i INNER JOIN ' . $def['table'] . ' rel ON rel.content_id = i.content_id WHERE rel.' . $def['id'] . ' = :taxonomy_id AND i.content_id <> :content_id ORDER BY i.release_date DESC, i.updated_at DESC, i.id DESC LIMIT 8';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':taxonomy_id' => $taxonomyId, ':content_id' => $contentId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $relatedItems[(string)$row['content_id']] = $row;
                if (count($relatedItems) >= 12) {
                    break 2;
                }
            }
        }

        if ($relatedItems !== []) {
            $sections[] = ['label' => $label, 'items' => array_values($relatedItems)];
        }
    }

    if ($sections === []) {
        $fallback = fetch_related_items($contentId, 12);
        if ($fallback !== []) {
            $sections[] = ['label' => '関連商品', 'items' => $fallback];
        }
    }

    return $sections;
}

$id = (int)get('id', 0);
$contentId = trim((string)get('content_id', ''));
$dbError = false;
$item = null;
$relatedSections = [];

try {
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

    $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($item === null) {
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
    $relatedSections = item_fetch_related_sections(db(), $item);
} catch (Throwable $e) {
    $dbError = true;
    error_log('public/item.php load failed: ' . $e->getMessage());
}

$title = $item !== null ? (string)($item['title'] ?? '作品詳細') : '作品詳細';
$raw = $item !== null ? item_decode_raw($item) : [];
$sampleMovieUrl = $item !== null ? item_pick_sample_movie_url($item, $raw) : '';
$sampleImages = $item !== null ? item_sample_images($raw) : [];
$affiliateUrl = $item !== null ? item_affiliate_link($item) : '';
$imageLarge = trim((string)($item['image_large'] ?? ''));
$imageSmall = trim((string)($item['image_small'] ?? ''));
$imageList = trim((string)($item['image_list'] ?? ''));
$primaryImage = $imageLarge !== '' ? $imageLarge : ($imageSmall !== '' ? $imageSmall : $imageList);

require __DIR__ . '/partials/header.php';
?>
<?php if ($dbError || $item === null): ?>
  <div class="card"><p>DB接続に失敗しました（設定を確認してください）。管理画面のAPI設定をご確認ください。</p></div>
<?php else: ?>
  <section class="item-page">
    <div class="item-video-hero">
      <?php if ($sampleMovieUrl !== ''): ?>
        <a href="<?= e($sampleMovieUrl) ?>" target="_blank" rel="noopener noreferrer" class="item-main-affiliate">サンプル動画を見る</a>
      <?php else: ?>
        <button type="button" class="item-main-affiliate item-main-affiliate--disabled" disabled>サンプル動画なし</button>
      <?php endif; ?>
    </div>

    <div class="item-detail-grid card">
      <div class="item-detail-grid__image">
        <?php if ($primaryImage !== ''): ?>
          <img src="<?= e($primaryImage) ?>" class="item-package-image" alt="<?= e((string)$item['title']) ?>">
        <?php else: ?>
          <div class="rail-card__noimage">画像なし</div>
        <?php endif; ?>
      </div>
      <div class="item-detail-grid__meta">
        <h2><?= e((string)$item['title']) ?></h2>
        <ul>
          <li>発売日: <?= e((string)($item['release_date'] ?? $item['date_published'] ?? '-')) ?></li>
          <li>収録時間: <?= e((string)($item['runtime'] ?? '-')) ?></li>
          <li>価格: <?= e((string)($item['price_min_text'] ?? $item['price_min'] ?? '-')) ?></li>
          <li>レビュー: <?= e((string)($item['review_average'] ?? '-')) ?> (<?= e((string)($item['review_count'] ?? '0')) ?>件)</li>
          <li>メーカー: <?= e(item_names_from_iteminfo($raw, 'maker')) ?></li>
        </ul>
      </div>
    </div>

    <div class="item-main-affiliate-wrap">
      <?php if ($affiliateUrl !== ''): ?>
        <a href="<?= e($affiliateUrl) ?>" target="_blank" rel="noopener noreferrer" class="item-main-affiliate">アフィリエイトリンク</a>
      <?php else: ?>
        <button type="button" class="item-main-affiliate item-main-affiliate--disabled" disabled>アフィリエイトリンク（準備中）</button>
      <?php endif; ?>
    </div>

    <section class="card">
      <h3>サンプル画像</h3>
      <?php if ($sampleImages === []): ?>
        <p>サンプル画像はありません。</p>
      <?php else: ?>
        <div class="item-sample-grid">
          <?php foreach ($sampleImages as $index => $image): ?>
            <a href="<?= e(public_url('sample_images.php?content_id=' . rawurlencode((string)$item['content_id']) . '&index=' . ($index + 1))) ?>" target="_blank" rel="noopener noreferrer">
              <img src="<?= e($image) ?>" alt="サンプル画像 <?= e((string)($index + 1)) ?>">
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <div class="item-main-affiliate-wrap">
      <?php if ($affiliateUrl !== ''): ?>
        <a href="<?= e($affiliateUrl) ?>" target="_blank" rel="noopener noreferrer" class="item-main-affiliate">購入する</a>
      <?php else: ?>
        <button type="button" class="item-main-affiliate item-main-affiliate--disabled" disabled>購入リンクなし</button>
      <?php endif; ?>
    </div>

    <?php foreach ($relatedSections as $section): ?>
      <section class="rail-section">
        <h3><?= e((string)$section['label']) ?></h3>
        <div class="rail-row rail-row--180">
          <?php foreach ((array)$section['items'] as $related): ?>
            <?php
              $relatedRaw = item_decode_raw($related);
              $relatedMovieUrl = item_pick_sample_movie_url($related, $relatedRaw);
              $relatedImages = item_sample_images($relatedRaw);
              $relatedAffiliateUrl = item_affiliate_link($related);
            ?>
            <article class="card rail-card rail-card--180">
              <?php if (!empty($related['image_small'])): ?>
                <img class="thumb" src="<?= e((string)$related['image_small']) ?>" alt="<?= e((string)($related['title'] ?? '')) ?>">
              <?php else: ?>
                <div class="rail-card__noimage">画像なし</div>
              <?php endif; ?>
              <a class="rail-card__title" href="<?= e(public_url('item.php?id=' . (int)$related['id'])) ?>"><?= e((string)($related['title'] ?? '')) ?></a>
              <div class="sample-buttons">
                <?= item_render_action_link('サンプル動画', $relatedMovieUrl) ?>
                <?= item_render_action_link('サンプル画像', $relatedImages !== [] ? public_url('sample_images.php?content_id=' . rawurlencode((string)$related['content_id'])) : '') ?>
                <?= item_render_action_link('アフィリエイトリンク', $relatedAffiliateUrl) ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
