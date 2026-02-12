<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/repository.php';

$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($pageNum - 1) * $perPage;

// Handle tag deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tag_id'])) {
    $token = $_POST['_token'] ?? null;
    if (csrf_verify(is_string($token) ? $token : null)) {
        $tagId = (int)($_POST['delete_tag_id'] ?? 0);
        if ($tagId > 0) {
            try {
                $stmt = db()->prepare('DELETE FROM tags WHERE id = :id');
                $stmt->execute([':id' => $tagId]);
                header('Location: tags.php');
                exit;
            } catch (PDOException $e) {
                error_log('Delete tag error: ' . $e->getMessage());
            }
        }
    }
}

// Fetch tags
$tags = fetch_all_tags($perPage, $offset);
$totalTags = 0;

try {
    $stmt = db()->query('SELECT COUNT(*) FROM tags');
    $totalTags = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('count tags error: ' . $e->getMessage());
}

$totalPages = max(1, (int)ceil($totalTags / $perPage));

$pageTitle = 'タグ管理';
ob_start();
?>
<h1>タグ管理</h1>

<div class="admin-card">
    <p>自動生成されたタグの一覧です。不要なタグを削除できます。</p>
    <?php if ($totalTags > 0) : ?>
        <p><strong>合計:</strong> <?php echo e((string)$totalTags); ?>個のタグ</p>
    <?php endif; ?>
</div>

<?php if (empty($tags)) : ?>
    <div class="admin-card">
        <p>タグがありません。インポートを実行するとタグが自動生成されます。</p>
    </div>
<?php else : ?>
    <div class="admin-card">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px; text-align: left;">ID</th>
                    <th style="padding: 8px; text-align: left;">タグ名</th>
                    <th style="padding: 8px; text-align: center;">アイテム数</th>
                    <th style="padding: 8px; text-align: left;">作成日時</th>
                    <th style="padding: 8px; text-align: center;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $tag) : ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px;"><?php echo e((string)($tag['id'] ?? '')); ?></td>
                        <td style="padding: 8px; font-weight: bold;">
                            <?php echo e((string)($tag['name'] ?? '')); ?>
                        </td>
                        <td style="padding: 8px; text-align: center;">
                            <?php echo e((string)($tag['item_count'] ?? 0)); ?>
                        </td>
                        <td style="padding: 8px;">
                            <?php echo e((string)($tag['created_at'] ?? '')); ?>
                        </td>
                        <td style="padding: 8px; text-align: center;">
                            <form method="post" style="display: inline;" onsubmit="return confirm('このタグを削除しますか？');">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="delete_tag_id" value="<?php echo e((string)($tag['id'] ?? '')); ?>">
                                <button type="submit" style="padding: 4px 8px; background: #dc3545; color: white; border: none; cursor: pointer; border-radius: 3px;">削除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1) : ?>
        <div class="admin-card" style="text-align: center;">
            <div style="display: inline-flex; gap: 8px; align-items: center;">
                <?php if ($pageNum > 1) : ?>
                    <a href="?page=<?php echo e((string)($pageNum - 1)); ?>" style="padding: 4px 12px; border: 1px solid #ddd; text-decoration: none;">« 前</a>
                <?php endif; ?>
                
                <span>ページ <?php echo e((string)$pageNum); ?> / <?php echo e((string)$totalPages); ?></span>
                
                <?php if ($pageNum < $totalPages) : ?>
                    <a href="?page=<?php echo e((string)($pageNum + 1)); ?>" style="padding: 4px 12px; border: 1px solid #ddd; text-decoration: none;">次 »</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
