<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = 'アクセス解析（詳細）';

$limit = 100;

$inStmt = db()->prepare('SELECT created_at,referer_host,path FROM in_logs ORDER BY id DESC LIMIT :limit');
$inStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$inStmt->execute();
$inRows = $inStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$outStmt = db()->prepare('SELECT created_at,target_url,path FROM out_logs ORDER BY id DESC LIMIT :limit');
$outStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$outStmt->execute();
$outRows = $outStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-card">
  <h1>アクセス解析（詳細）</h1>

  <p>※この画面のうち、現在実データで計測済みなのは「リンク元」「移動先」です。
  「検索エンジン」「検索ワード」「滞在時間」は既存ログテーブル未保存のため集計できません（要確認）。</p>

  <h2>流入アクセス（最新<?= e((string)$limit) ?>件）</h2>
  <table class="admin-table"><tr><th>日時</th><th>リンク元</th><th>移動先</th><th>検索エンジン</th><th>検索ワード</th><th>滞在時間</th></tr>
    <?php foreach ($inRows as $row): ?>
      <tr>
        <td><?= e((string)$row['created_at']) ?></td>
        <td><?= e((string)($row['referer_host'] ?? '')) ?></td>
        <td><?= e((string)($row['path'] ?? '')) ?></td>
        <td>-</td>
        <td>-</td>
        <td>-</td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>クリック流出（最新<?= e((string)$limit) ?>件）</h2>
  <table class="admin-table"><tr><th>日時</th><th>リンク元</th><th>移動先</th><th>検索エンジン</th><th>検索ワード</th><th>滞在時間</th></tr>
    <?php foreach ($outRows as $row): ?>
      <tr>
        <td><?= e((string)$row['created_at']) ?></td>
        <td><?= e((string)($row['path'] ?? '')) ?></td>
        <td><?= e((string)($row['target_url'] ?? '')) ?></td>
        <td>-</td>
        <td>-</td>
        <td>-</td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
