<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';
require_once __DIR__ . '/../lib/repository.php';

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


$accessRankingRows = [];
try {
    $rankingStmt = db()->prepare('SELECT id, title, view_count FROM items ORDER BY view_count DESC, id DESC LIMIT 200');
    $rankingStmt->execute();
    $accessRankingRows = $rankingStmt->fetchAll() ?: [];
} catch (Throwable) {
    $accessRankingRows = [];
}

$title = '商品一覧';
require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero('商品一覧', '最新の作品を一覧でチェックできます。'); ?>

<?php if ($rows !== []): ?>
  <section class="rail-section">
    <div class="rail-row rail-row--200 rail-row--wide-thumb">
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
    </div>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>


<section class="block" style="margin-top:24px;">
  <h2 class="section-title">アクセスランキング</h2>
  <?php if ($accessRankingRows !== []): ?>
    <div style="max-height:800px; overflow-y:auto; border:1px solid #ddd;">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">順位</th>
            <th style="text-align:left; padding:8px; border-bottom:1px solid #ddd;">作品タイトル</th>
            <th style="text-align:right; padding:8px; border-bottom:1px solid #ddd;">アクセス数</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessRankingRows as $index => $rankingRow): ?>
            <tr>
              <td style="padding:8px; border-bottom:1px solid #eee;"><?= e((string)($index + 1)) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee;"><?= e((string)($rankingRow['title'] ?? '')) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:right;"><?= e((string)((int)($rankingRow['view_count'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?php pcf_render_empty('アクセスランキングのデータがありません。'); ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>