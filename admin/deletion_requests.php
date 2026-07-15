<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = '削除依頼一覧';
$pdo = db();

$exists = db_table_exists('deletion_requests');
$rows = [];
if ($exists) {
    $stmt = $pdo->query('SELECT id, receipt_number, requester_name, requester_email, requester_phone, page_urls, reason, document_mime, document_original_name, document_delivery, status, created_at FROM deletion_requests ORDER BY id DESC LIMIT 100');
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>削除依頼一覧</h1>
  <p>本人確認書類は受付メールにのみ添付され、サーバーには保存されません。</p>
  <?php if (!$exists): ?>
    <p>削除依頼はまだありません。</p>
  <?php else: ?>
    <table class="admin-table">
      <tr><th>受付番号</th><th>申請者</th><th>該当ページ・理由</th><th>受付日時</th><th>本人確認書類</th></tr>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string)$row['receipt_number']) ?><br><small><?= e((string)$row['status']) ?></small></td>
          <td><?= e((string)$row['requester_name']) ?><br><?= e((string)$row['requester_email']) ?><br><?= e((string)($row['requester_phone'] ?? '')) ?></td>
          <td style="white-space:pre-wrap;max-width:520px;"><strong>URL</strong><br><?= e((string)$row['page_urls']) ?><br><br><strong>理由</strong><br><?= e((string)$row['reason']) ?></td>
          <td><?= e((string)$row['created_at']) ?></td>
          <td>メール添付のみ<br><small><?= e((string)($row['document_original_name'] ?? '')) ?></small></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
