<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';
auth_require_admin();
$title = '削除依頼一覧';
$pdo = db();

if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];
    $stmt = $pdo->prepare('SELECT receipt_number, document_path, document_mime, document_original_name FROM deletion_requests WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        http_response_code(404);
        exit('書類が見つかりません。');
    }
    $baseDir = realpath(dirname(__DIR__) . '/storage/private/deletion_requests');
    $path = $baseDir !== false ? realpath($baseDir . '/' . basename((string)$row['document_path'])) : false;
    if ($baseDir === false || $path === false || !str_starts_with($path, $baseDir . DIRECTORY_SEPARATOR) || !is_file($path)) {
        http_response_code(404);
        exit('書類が見つかりません。');
    }
    header('Content-Type: ' . (string)$row['document_mime']);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="identity-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$row['receipt_number']) . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$exists = db_table_exists('deletion_requests');
$rows = [];
if ($exists) {
    $stmt = $pdo->query('SELECT id, receipt_number, requester_name, requester_email, requester_phone, page_urls, reason, status, created_at FROM deletion_requests ORDER BY id DESC LIMIT 100');
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>削除依頼一覧</h1>
  <p>本人確認書類は公開領域外に保存され、管理者ログイン時のみダウンロードできます。</p>
  <?php if (!$exists): ?>
    <p>削除依頼はまだありません。</p>
  <?php else: ?>
    <table class="admin-table">
      <tr><th>受付番号</th><th>申請者</th><th>該当ページ・理由</th><th>受付日時</th><th>書類</th></tr>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string)$row['receipt_number']) ?><br><small><?= e((string)$row['status']) ?></small></td>
          <td><?= e((string)$row['requester_name']) ?><br><?= e((string)$row['requester_email']) ?><br><?= e((string)($row['requester_phone'] ?? '')) ?></td>
          <td style="white-space:pre-wrap;max-width:520px;"><strong>URL</strong><br><?= e((string)$row['page_urls']) ?><br><br><strong>理由</strong><br><?= e((string)$row['reason']) ?></td>
          <td><?= e((string)$row['created_at']) ?></td>
          <td><a href="<?= e(admin_url('deletion_requests.php?download=' . (string)$row['id'])) ?>">本人確認書類</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
