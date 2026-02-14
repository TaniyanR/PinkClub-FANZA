<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:links.php');

function links_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE :column');
    $stmt->execute([':column' => $column]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function links_validate_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

$error = '';
$ok = (string)($_GET['ok'] ?? '');
$hasTable = admin_table_exists('mutual_links');
$hasDisplayOrder = $hasTable && links_column_exists('mutual_links', 'display_order');
$hasIsEnabled = $hasTable && links_column_exists('mutual_links', 'is_enabled');
$hasStatus = $hasTable && links_column_exists('mutual_links', 'status');
$editId = max(0, (int)($_GET['edit'] ?? 0));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } elseif (!$hasTable) {
        $error = 'mutual_links テーブルが未作成のため保存できません。';
    } else {
        $action = (string)($_POST['action'] ?? 'create');
        $title = trim((string)($_POST['title'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $sortOrder = filter_var($_POST['sort_order'] ?? '100', FILTER_VALIDATE_INT);
        $sortOrder = ($sortOrder === false) ? 100 : (int)$sortOrder;
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db()->prepare('DELETE FROM mutual_links WHERE id=:id')->execute([':id' => $id]);
                header('Location: ' . admin_url('links.php?ok=deleted'));
                exit;
            }
            $error = '削除対象が不正です。';
        } elseif (in_array($action, ['create', 'update'], true)) {
            if ($title === '' || mb_strlen($title) > 200) {
                $error = 'タイトルは1〜200文字で入力してください。';
            } elseif (!links_validate_url($url)) {
                $error = 'URLは http(s):// 形式で入力してください。';
            } else {
                if ($action === 'create') {
                    $columns = ['site_name', 'site_url', 'link_url', 'created_at', 'updated_at'];
                    $placeholders = [':site_name', ':site_url', ':link_url', 'NOW()', 'NOW()'];
                    $params = [
                        ':site_name' => $title,
                        ':site_url' => $url,
                        ':link_url' => $url,
                    ];
                    if ($hasDisplayOrder) {
                        $columns[] = 'display_order';
                        $placeholders[] = ':display_order';
                        $params[':display_order'] = $sortOrder;
                    }
                    if ($hasIsEnabled) {
                        $columns[] = 'is_enabled';
                        $placeholders[] = ':is_enabled';
                        $params[':is_enabled'] = $isEnabled;
                    }
                    if ($hasStatus) {
                        $columns[] = 'status';
                        $placeholders[] = "'approved'";
                    }
                    $sql = 'INSERT INTO mutual_links (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                    db()->prepare($sql)->execute($params);
                    header('Location: ' . admin_url('links.php?ok=created'));
                    exit;
                }

                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $error = '更新対象が不正です。';
                } else {
                    $sets = [
                        'site_name=:site_name',
                        'site_url=:site_url',
                        'link_url=:link_url',
                        'updated_at=NOW()',
                    ];
                    $params = [
                        ':id' => $id,
                        ':site_name' => $title,
                        ':site_url' => $url,
                        ':link_url' => $url,
                    ];
                    if ($hasDisplayOrder) {
                        $sets[] = 'display_order=:display_order';
                        $params[':display_order'] = $sortOrder;
                    }
                    if ($hasIsEnabled) {
                        $sets[] = 'is_enabled=:is_enabled';
                        $params[':is_enabled'] = $isEnabled;
                    }
                    if ($hasStatus) {
                        $sets[] = "status='approved'";
                    }
                    $sql = 'UPDATE mutual_links SET ' . implode(', ', $sets) . ' WHERE id=:id';
                    db()->prepare($sql)->execute($params);
                    header('Location: ' . admin_url('links.php?ok=updated'));
                    exit;
                }
            }
        }
    }
}

$rows = [];
$form = [
    'id' => 0,
    'title' => '',
    'url' => '',
    'sort_order' => '100',
    'is_enabled' => '1',
];

if ($hasTable) {
    try {
        $orderBy = $hasDisplayOrder ? 'display_order ASC, id ASC' : 'id ASC';
        $rows = db()->query('SELECT * FROM mutual_links ORDER BY ' . $orderBy . ' LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);

        if ($editId > 0) {
            $stmt = db()->prepare('SELECT * FROM mutual_links WHERE id=:id LIMIT 1');
            $stmt->execute([':id' => $editId]);
            $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($editRow)) {
                $form = [
                    'id' => (string)$editRow['id'],
                    'title' => (string)($editRow['site_name'] ?? ''),
                    'url' => (string)($editRow['site_url'] ?? ''),
                    'sort_order' => (string)($editRow['display_order'] ?? '100'),
                    'is_enabled' => ((int)($editRow['is_enabled'] ?? 1) === 1) ? '1' : '0',
                ];
            }
        }
    } catch (Throwable $e) {
        $error = '相互リンクデータの読み込みに失敗しました。';
        $rows = [];
    }
}

$pageTitle = '相互リンク管理';
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($ok, $error, $hasTable, $rows, $hasDisplayOrder, $hasIsEnabled, $form): void {
    ?>
    <h1>相互リンク管理</h1>
    
    <?php if ($ok !== '') : ?>
        <div class="admin-card" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724; padding: 12px; margin-bottom: 16px;">
            <p style="margin: 0;">
                <?php if ($ok === 'created') : ?>登録しました。
                <?php elseif ($ok === 'updated') : ?>更新しました。
                <?php elseif ($ok === 'deleted') : ?>削除しました。
                <?php else : ?>処理が完了しました。
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if ($error !== '') : ?>
        <div class="admin-card" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 12px; margin-bottom: 16px;">
            <p style="margin: 0;"><?php echo e($error); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!$hasTable) : ?>
        <div class="admin-card">
            <p>mutual_links テーブルが存在しません。db_init.php でテーブルを作成してください。</p>
        </div>
    <?php else : ?>
        <div class="admin-card">
            <h2><?php echo ((int)$form['id'] > 0) ? '相互リンクを編集' : '相互リンクを追加'; ?></h2>
            <form method="post">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="<?php echo ((int)$form['id'] > 0) ? 'update' : 'create'; ?>">
                <?php if ((int)$form['id'] > 0) : ?>
                    <input type="hidden" name="id" value="<?php echo e($form['id']); ?>">
                <?php endif; ?>
                
                <label>サイト名 <span style="color: #d00;">*</span></label>
                <input type="text" name="title" value="<?php echo e($form['title']); ?>" required maxlength="200" style="width: 100%; max-width: 500px;">
                
                <label>URL <span style="color: #d00;">*</span></label>
                <input type="url" name="url" value="<?php echo e($form['url']); ?>" required style="width: 100%; max-width: 500px;" placeholder="https://example.com">
                
                <?php if ($hasDisplayOrder) : ?>
                    <label>表示順序</label>
                    <input type="number" name="sort_order" value="<?php echo e($form['sort_order']); ?>" min="0" max="9999" style="width: 150px;">
                    <p style="margin: 4px 0 12px 0; font-size: 0.9em; color: #666;">小さい数値ほど上位に表示されます</p>
                <?php endif; ?>
                
                <?php if ($hasIsEnabled) : ?>
                    <label>
                        <input type="checkbox" name="is_enabled" value="1" <?php echo ($form['is_enabled'] === '1') ? 'checked' : ''; ?>>
                        有効化（チェックを外すと非表示になります）
                    </label>
                <?php endif; ?>
                
                <div style="margin-top: 16px;">
                    <button type="submit" style="padding: 8px 24px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; font-size: 14px;">
                        <?php echo ((int)$form['id'] > 0) ? '更新' : '登録'; ?>
                    </button>
                    <?php if ((int)$form['id'] > 0) : ?>
                        <a href="<?php echo e(admin_url('links.php')); ?>" style="margin-left: 8px; padding: 8px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 14px;">キャンセル</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="admin-card">
            <h2>登録済み相互リンク (<?php echo e((string)count($rows)); ?>件)</h2>
            <?php if (count($rows) === 0) : ?>
                <p>相互リンクが登録されていません。</p>
            <?php else : ?>
                <table class="admin-table" style="width: 100%; border-collapse: collapse; margin-top: 12px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd; background-color: #f8f9fa;">
                            <th style="padding: 10px; text-align: left; width: 60px;">ID</th>
                            <th style="padding: 10px; text-align: left;">サイト名</th>
                            <th style="padding: 10px; text-align: left;">URL</th>
                            <?php if ($hasDisplayOrder) : ?>
                                <th style="padding: 10px; text-align: center; width: 80px;">順序</th>
                            <?php endif; ?>
                            <?php if ($hasIsEnabled) : ?>
                                <th style="padding: 10px; text-align: center; width: 80px;">状態</th>
                            <?php endif; ?>
                            <th style="padding: 10px; text-align: center; width: 150px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 10px;"><?php echo e((string)$row['id']); ?></td>
                                <td style="padding: 10px; font-weight: 500;"><?php echo e((string)($row['site_name'] ?? '')); ?></td>
                                <td style="padding: 10px;">
                                    <a href="<?php echo e((string)($row['site_url'] ?? '')); ?>" target="_blank" rel="noopener" style="color: #007bff; text-decoration: none;">
                                        <?php echo e((string)($row['site_url'] ?? '')); ?>
                                    </a>
                                </td>
                                <?php if ($hasDisplayOrder) : ?>
                                    <td style="padding: 10px; text-align: center;"><?php echo e((string)($row['display_order'] ?? '100')); ?></td>
                                <?php endif; ?>
                                <?php if ($hasIsEnabled) : ?>
                                    <td style="padding: 10px; text-align: center;">
                                        <?php if (((int)($row['is_enabled'] ?? 1)) === 1) : ?>
                                            <span style="color: #28a745; font-weight: 500;">●</span>
                                        <?php else : ?>
                                            <span style="color: #dc3545;">○</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 10px; text-align: center;">
                                    <a href="<?php echo e(admin_url('links.php?edit=' . (string)$row['id'])); ?>" style="padding: 4px 12px; background: #ffc107; color: #000; text-decoration: none; border-radius: 3px; display: inline-block; margin-right: 4px; font-size: 13px;">編集</a>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('「<?php echo e((string)($row['site_name'] ?? '')); ?>」を削除してもよろしいですか？');">
                                        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo e((string)$row['id']); ?>">
                                        <button type="submit" style="padding: 4px 12px; background: #dc3545; color: white; border: none; cursor: pointer; border-radius: 3px; font-size: 13px;">削除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
});
