<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';
require_once __DIR__ . '/../lib/repository.php';

$page = max(1, (int)get('page', 1));
$per = 32;
$total = 0;
$rows = [];
$sourceWhere = function_exists('items_product_source_where') ? items_product_source_where() : '';
$sourceWhereSql = $sourceWhere !== '' ? ' WHERE ' . $sourceWhere : '';

try {
    $total = (int)db()->query('SELECT COUNT(*) FROM items' . $sourceWhereSql)->fetchColumn();
} catch (Throwable) {
    $total = 0;
}

$pg = paginate($total, $page, (int)$per);

try {
    $stmt = db()->prepare('SELECT * FROM items' . $sourceWhereSql . ' ORDER BY created_at DESC, id DESC LIMIT :l OFFSET :o');
    $stmt->bindValue(':l', (int)$pg['perPage'], PDO::PARAM_INT);
    $stmt->bindValue(':o', (int)$pg['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];
} catch (Throwable) {
    $rows = [];
}

$accessRankingPeriod = trim((string)get('rank_period', 'daily'));
$accessRankingTabs = [
    'daily' => ['label' => '24時間', 'where' => 'pv.viewed_at >= (NOW() - INTERVAL 1 DAY)'],
    'weekly' => ['label' => '週間', 'where' => 'pv.viewed_at >= (NOW() - INTERVAL 7 DAY)'],
    'monthly' => ['label' => '月間', 'where' => 'pv.viewed_at >= (NOW() - INTERVAL 1 MONTH)'],
    'yearly' => ['label' => '年間', 'where' => 'pv.viewed_at >= (NOW() - INTERVAL 1 YEAR)'],
];
if (!isset($accessRankingTabs[$accessRankingPeriod])) {
    $accessRankingPeriod = 'daily';
}
$accessRankingRows = [];
try {
    $periodFrom = null;
    if ($accessRankingPeriod === 'daily') {
        $periodFrom = date('Y-m-d H:i:s', strtotime('-24 hours'));
    } elseif ($accessRankingPeriod === 'weekly') {
        $periodFrom = date('Y-m-d H:i:s', strtotime('-7 days'));
    } elseif ($accessRankingPeriod === 'monthly') {
        $periodFrom = date('Y-m-d H:i:s', strtotime('-1 month'));
    } elseif ($accessRankingPeriod === 'yearly') {
        $periodFrom = date('Y-m-d H:i:s', strtotime('-1 year'));
    }

    if ($periodFrom === null) {
        $periodFrom = date('Y-m-d H:i:s', strtotime('-24 hours'));
    }

    $rankingStmt = db()->prepare('SELECT i.id, i.content_id, i.title, COUNT(pv.id) AS access_count FROM page_views pv INNER JOIN items i ON i.id = pv.item_id WHERE pv.viewed_at >= :period_from GROUP BY i.id, i.title ORDER BY access_count DESC, i.id DESC LIMIT 200');
    $rankingStmt->execute([':period_from' => $periodFrom]);
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
    <div class="rail-row rail-row--200 rail-row--wide-thumb rail-row--items-grid" style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; overflow:visible; padding-bottom:0;">
    <?php foreach ($rows as $r): ?>
      <?php
      $itemRow = is_array($r) ? $r : [];
      pcf_render_item_card($itemRow, 200, true);
      ?>
    <?php endforeach; ?>
    </div>
  </section>
  <?php pcf_render_pagination($pg, public_url('items.php')); ?>
<?php else: ?>
  <?php pcf_render_empty('商品データがまだ登録されていません。'); ?>
<?php endif; ?>

<section id="access-ranking" class="block" style="margin-top:24px;">
  <h2 class="section-title">アクセスランキング</h2>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <?php foreach ($accessRankingTabs as $tabKey => $tabConfig): ?>
      <?php $tabUrl = public_url(basename(__FILE__)) . '?rank_period=' . rawurlencode((string)$tabKey) . '#access-ranking'; ?>
      <?php $tabStyle = $accessRankingPeriod === $tabKey ? 'display:inline-block; padding:6px 12px; border:1px solid #0b5ed7; border-radius:6px; background:#0b5ed7; color:#fff; font-weight:700; text-decoration:none;' : 'display:inline-block; padding:6px 12px; border:1px solid #0b5ed7; border-radius:6px; background:#fff; color:#0b5ed7; font-weight:700; text-decoration:none;'; ?>
      <a href="<?= e($tabUrl) ?>" style="<?= e($tabStyle) ?>"><?= e((string)$tabConfig['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($accessRankingRows !== []): ?>
    <div style="max-height:800px; overflow-y:auto; border:1px solid #ddd;">
      <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <thead>
          <tr>
            <th style="width:80px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">順位</th>
            <th style="width:auto; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">作品タイトル</th>
            <th style="width:120px; text-align:center; padding:8px; border-bottom:1px solid #ddd; background:#0b5ed7; color:#fff;">アクセス数</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($accessRankingRows as $index => $rankingRow): ?>
            <tr>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)($index + 1)) ?></td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:left;">
                <?php
                $rankingItemUrl = public_url('item.php') . '?id=' . rawurlencode((string)($rankingRow['id'] ?? ''));
                ?>
                <a href="<?= e($rankingItemUrl) ?>"><?= e((string)($rankingRow['title'] ?? '')) ?></a>
              </td>
              <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;"><?= e((string)((int)($rankingRow['access_count'] ?? 0))) ?></td>
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