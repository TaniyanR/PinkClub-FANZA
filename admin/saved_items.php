<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/_bootstrap.php';

auth_require_admin();

$title = '保存済み商品一覧';
$message = '';
$messageType = 'success';
$perPage = 50;
$page = max(1, (int)get('page', 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail((string)post('_csrf', ''));
    $action = (string)post('action', '');

    if ($action === 'delete_item') {
        $id = (int)post('item_id', 0);
        if ($id > 0) {
            $stmt = db()->prepare('SELECT id, image_list, image_small, image_large FROM items WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($item)) {
                $imagePaths = [];
                foreach (['image_list', 'image_small', 'image_large'] as $column) {
                    $value = trim((string)($item[$column] ?? ''));
                    if ($value !== '') {
                        $imagePaths[] = $value;
                    }
                }

                db()->prepare('DELETE FROM items WHERE id = :id')->execute([':id' => $id]);

                $deletedImageCount = 0;
                $publicRoot = realpath(__DIR__ . '/../public');
                foreach (array_unique($imagePaths) as $imagePath) {
                    $parts = parse_url($imagePath);
                    if (!is_array($parts)) {
                        continue;
                    }
                    $path = (string)($parts['path'] ?? '');
                    if ($path === '') {
                        continue;
                    }
                    $normalized = ltrim($path, '/');
                    if ($normalized === '') {
                        continue;
                    }
                    $fullPath = realpath(__DIR__ . '/../public/' . $normalized);
                    if ($publicRoot === false || $fullPath === false) {
                        continue;
                    }
                    if (strpos($fullPath, $publicRoot . DIRECTORY_SEPARATOR) !== 0) {
                        continue;
                    }
                    if (is_file($fullPath) && @unlink($fullPath)) {
                        $deletedImageCount++;
                    }
                }

                $message = '商品を削除しました。';
                if ($deletedImageCount > 0) {
                    $message .= ' 画像ファイル削除数: ' . (string)$deletedImageCount;
                }
                $messageType = 'success';
            }
        }
    }
}

$totalCountStmt = db()->query('SELECT COUNT(*) FROM items');
$totalCount = (int)($totalCountStmt ? $totalCountStmt->fetchColumn() : 0);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$listStmt = db()->prepare('SELECT id, content_id, title, updated_at FROM items ORDER BY id DESC LIMIT :limit OFFSET :offset');
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$savedRows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($savedRows as &$row) {
    $rowName = trim((string)($row['title'] ?? ''));
    if ($rowName === '') {
        $rowName = '（名称未設定）';
    }
    $row['row_name'] = $rowName;

    $rowContentId = trim((string)($row['content_id'] ?? ''));
    $row['row_content_id'] = $rowContentId;

    $rowUpdatedAt = trim((string)($row['updated_at'] ?? ''));
    if ($rowUpdatedAt === '') {
        $rowUpdatedAt = '---';
    }
    $row['row_updated_at'] = $rowUpdatedAt;
}
unset($row);

$startPage = max(1, $page - 2);
$endPage = min($totalPages, $startPage + 4);
if ($endPage - $startPage < 4) {
    $startPage = max(1, $endPage - 4);
}

require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <h1>保存済み商品一覧</h1>

  <?php if ($message !== ''): ?>
    <div class="admin-notice <?= $messageType === 'success' ? 'admin-notice--success' : 'admin-notice--error' ?>">
      <p><?= e($message) ?></p>
    </div>
  <?php endif; ?>

  <p><a href="<?= e(admin_url('api_items.php')) ?>">商品情報API設定に戻る</a></p>

  <table class="admin-table">
    <tr><th>ID</th><th>名称</th><th>更新日時</th><th>操作</th></tr>
    <?php foreach ($savedRows as $row): ?>
      <tr>
        <td><?= e((string)($row['id'] ?? '')) ?></td>
        <td>
          <?php if ((string)($row['row_content_id'] ?? '') !== ''): ?>
            <a href="<?= e(base_url() . '/item.php?cid=' . rawurlencode((string)$row['row_content_id'])) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)($row['row_name'] ?? '')) ?></a>
          <?php else: ?>
            <?= e((string)($row['row_name'] ?? '')) ?>
          <?php endif; ?>
        </td>
        <td><?= e((string)($row['row_updated_at'] ?? '---')) ?></td>
        <td>
          <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete_item">
            <input type="hidden" name="item_id" value="<?= e((string)($row['id'] ?? '0')) ?>">
            <button type="submit" class="button-secondary">削除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($totalPages > 1): ?>
    <nav style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
        <?php if ($p === $page): ?>
          <strong><?= e((string)$p) ?></strong>
        <?php else: ?>
          <a href="<?= e(admin_url('saved_items.php?page=' . $p)) ?>"><?= e((string)$p) ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="<?= e(admin_url('saved_items.php?page=' . ($page + 1))) ?>">次</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
