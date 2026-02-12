<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/repository.php';

$pageNum = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($pageNum - 1) * $perPage;

// Fetch API logs
function fetch_api_logs(int $limit, int $offset): array
{
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    
    try {
        $stmt = db()->prepare(
            'SELECT * FROM api_logs 
             ORDER BY created_at DESC 
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('fetch_api_logs error: ' . $e->getMessage());
        return [];
    }
}

function count_api_logs(): int
{
    try {
        $stmt = db()->query('SELECT COUNT(*) FROM api_logs');
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('count_api_logs error: ' . $e->getMessage());
        return 0;
    }
}

$logs = fetch_api_logs($perPage, $offset);
$totalLogs = count_api_logs();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));

$pageTitle = 'API履歴';
ob_start();
?>
<h1>API履歴</h1>

<div class="admin-card">
    <p>DMM API の実行履歴を表示します。直近<?php echo e((string)$perPage); ?>件ずつ表示されます。</p>
    <?php if ($totalLogs > 0) : ?>
        <p><strong>合計:</strong> <?php echo e((string)$totalLogs); ?>件</p>
    <?php endif; ?>
</div>

<?php if (empty($logs)) : ?>
    <div class="admin-card">
        <p>API履歴がありません。インポートを実行するとここに履歴が表示されます。</p>
    </div>
<?php else : ?>
    <div class="admin-card">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px; text-align: left;">日時</th>
                    <th style="padding: 8px; text-align: left;">エンドポイント</th>
                    <th style="padding: 8px; text-align: center;">ステータス</th>
                    <th style="padding: 8px; text-align: center;">HTTPコード</th>
                    <th style="padding: 8px; text-align: center;">取得件数</th>
                    <th style="padding: 8px; text-align: left;">エラー</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <?php
                    $isSuccess = (int)($log['success'] ?? 0) === 1 || 
                                 in_array($log['status'] ?? '', ['success', 'ok', 'SUCCESS'], true);
                    $statusClass = $isSuccess ? 'success' : 'error';
                    $statusText = $isSuccess ? '成功' : '失敗';
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px;"><?php echo e((string)($log['created_at'] ?? '')); ?></td>
                        <td style="padding: 8px;">
                            <?php 
                            $endpoint = (string)($log['endpoint'] ?? '');
                            echo e(strlen($endpoint) > 50 ? substr($endpoint, 0, 50) . '...' : $endpoint); 
                            ?>
                        </td>
                        <td style="padding: 8px; text-align: center;">
                            <span style="color: <?php echo $isSuccess ? 'green' : 'red'; ?>; font-weight: bold;">
                                <?php echo e($statusText); ?>
                            </span>
                        </td>
                        <td style="padding: 8px; text-align: center;">
                            <?php echo e((string)($log['http_code'] ?? '-')); ?>
                        </td>
                        <td style="padding: 8px; text-align: center;">
                            <?php echo e((string)($log['item_count'] ?? 0)); ?>
                        </td>
                        <td style="padding: 8px; font-size: 0.9em;">
                            <?php 
                            $error = (string)($log['error_message'] ?? '');
                            if ($error !== '') {
                                echo e(strlen($error) > 60 ? substr($error, 0, 60) . '...' : $error); 
                            } else {
                                echo '-';
                            }
                            ?>
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

