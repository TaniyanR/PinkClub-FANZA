<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function item_show_redirect_with_error(string $message): never
{
    admin_flash_set('import_items_error', $message);
    header('Location: ' . admin_url('import_items.php'));
    exit;
}

function item_show_table_columns(PDO $pdo, string $table): array
{
    $columns = [];

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        if ($stmt === false) {
            return [];
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $field = $row['Field'] ?? '';
            if (is_string($field) && $field !== '') {
                $columns[$field] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('item_show columns fetch failed: ' . $e->getMessage());
    }

    return $columns;
}

function item_show_value_label(array $item, string $key): string
{
    $value = $item[$key] ?? null;
    if ($value === null) {
        return '-';
    }

    $text = trim((string)$value);
    return $text !== '' ? $text : '-';
}

$idParam = $_GET['id'] ?? null;
if (!is_string($idParam) || $idParam === '' || ctype_digit($idParam) === false) {
    item_show_redirect_with_error('詳細表示対象のIDが不正です。');
}

$itemId = (int)$idParam;
if ($itemId <= 0) {
    item_show_redirect_with_error('詳細表示対象のIDが不正です。');
}

$item = null;
$listError = '';
$existingColumns = [];

try {
    $pdo = db();
    $existingColumns = item_show_table_columns($pdo, 'items');

    $baseColumns = [
        'id', 'title', 'product_id', 'content_id', 'created_at', 'updated_at',
        'image_list', 'image_small', 'image_large', 'url', 'affiliate_url',
    ];
    $optionalColumns = ['status', 'description', 'summary', 'body', 'raw_json'];

    $selectColumns = [];
    foreach (array_merge($baseColumns, $optionalColumns) as $column) {
        if (isset($existingColumns[$column])) {
            $selectColumns[] = $column;
        }
    }

    if ($selectColumns === []) {
        item_show_redirect_with_error('詳細データの取得に必要なカラムが見つかりません。');
    }

    $sql = 'SELECT ' . implode(', ', $selectColumns) . ' FROM items WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $itemId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        item_show_redirect_with_error('指定されたデータが見つかりませんでした。');
    }

    $item = $row;
} catch (Throwable $e) {
    error_log('item_show fetch failed: ' . $e->getMessage());
    $listError = '詳細データの取得に失敗しました。';
}

if (!is_array($item) && $listError === '') {
    $listError = '詳細データの取得に失敗しました。';
}

$pageTitle = '取得データ詳細';
ob_start();
?>
<h1>取得済みデータ詳細</h1>

<div class="admin-card" style="margin-bottom:12px;">
    <a href="<?php echo e(admin_url('import_items.php')); ?>">一覧に戻る</a>
</div>

<?php if ($listError !== '') : ?>
    <div class="admin-card" style="background:#ffe7e7;padding:12px;margin-bottom:16px;">
        <p style="margin:0;"><?php echo e($listError); ?></p>
    </div>
<?php elseif (is_array($item)) : ?>
    <?php
    $title = item_show_value_label($item, 'title');
    $productId = item_show_value_label($item, 'product_id');
    $contentId = item_show_value_label($item, 'content_id');
    $status = item_show_value_label($item, 'status');
    $createdAt = item_show_value_label($item, 'created_at');
    $updatedAt = item_show_value_label($item, 'updated_at');
    $imageSmall = item_show_value_label($item, 'image_small');
    $imageList = item_show_value_label($item, 'image_list');
    $imageLarge = item_show_value_label($item, 'image_large');
    $url = item_show_value_label($item, 'url');
    $affiliateUrl = item_show_value_label($item, 'affiliate_url');
    $description = item_show_value_label($item, 'description');
    $summary = item_show_value_label($item, 'summary');
    $body = item_show_value_label($item, 'body');
    $rawJson = item_show_value_label($item, 'raw_json');
    ?>

    <div class="admin-card">
        <table style="width:100%;border-collapse:collapse;">
            <tbody>
            <tr><th style="width:220px;border:1px solid #ddd;padding:8px;text-align:left;">ID</th><td style="border:1px solid #ddd;padding:8px;"><?php echo e(item_show_value_label($item, 'id')); ?></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">タイトル</th><td style="border:1px solid #ddd;padding:8px;"><?php echo e($title); ?></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">商品ID</th><td style="border:1px solid #ddd;padding:8px;"><?php echo e($productId); ?></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">コンテンツID</th><td style="border:1px solid #ddd;padding:8px;"><?php echo e($contentId); ?></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">ステータス</th><td style="border:1px solid #ddd;padding:8px;"><?php echo e($status); ?></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">作成日時</th><td style="border:1px solid #ddd;padding:8px;"><?php echo e($createdAt); ?></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">更新日時</th><td style="border:1px solid #ddd;padding:8px;"><?php echo e($updatedAt); ?></td></tr>
            <tr>
                <th style="border:1px solid #ddd;padding:8px;text-align:left;">画像（small / list / large）</th>
                <td style="border:1px solid #ddd;padding:8px;">
                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start;">
                        <div>
                            <div>small: <?php echo e($imageSmall); ?></div>
                            <?php if ($imageSmall !== '-') : ?><img src="<?php echo e($imageSmall); ?>" alt="small" style="max-width:180px;height:auto;display:block;margin-top:6px;"><?php endif; ?>
                        </div>
                        <div>
                            <div>list: <?php echo e($imageList); ?></div>
                            <?php if ($imageList !== '-') : ?><img src="<?php echo e($imageList); ?>" alt="list" style="max-width:180px;height:auto;display:block;margin-top:6px;"><?php endif; ?>
                        </div>
                        <div>
                            <div>large: <?php echo e($imageLarge); ?></div>
                            <?php if ($imageLarge !== '-') : ?><img src="<?php echo e($imageLarge); ?>" alt="large" style="max-width:180px;height:auto;display:block;margin-top:6px;"><?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <th style="border:1px solid #ddd;padding:8px;text-align:left;">商品URL</th>
                <td style="border:1px solid #ddd;padding:8px;">
                    <?php if ($url !== '-') : ?>
                        <a href="<?php echo e($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($url); ?></a>
                    <?php else : ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th style="border:1px solid #ddd;padding:8px;text-align:left;">アフィリエイトURL</th>
                <td style="border:1px solid #ddd;padding:8px;">
                    <?php if ($affiliateUrl !== '-') : ?>
                        <a href="<?php echo e($affiliateUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo e($affiliateUrl); ?></a>
                    <?php else : ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">説明文</th><td style="border:1px solid #ddd;padding:8px;"><pre style="white-space:pre-wrap;margin:0;"><?php echo e($description); ?></pre></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">サマリ</th><td style="border:1px solid #ddd;padding:8px;"><pre style="white-space:pre-wrap;margin:0;"><?php echo e($summary); ?></pre></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">本文</th><td style="border:1px solid #ddd;padding:8px;"><pre style="white-space:pre-wrap;margin:0;"><?php echo e($body); ?></pre></td></tr>
            <tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">生データJSON</th><td style="border:1px solid #ddd;padding:8px;"><pre style="white-space:pre-wrap;margin:0;max-height:360px;overflow:auto;"><?php echo e($rawJson); ?></pre></td></tr>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
