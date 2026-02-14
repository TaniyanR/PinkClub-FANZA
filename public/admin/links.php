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
ob_start();
?>
<h1>相互リンク管理</h1>

<?php if ($ok !== '') : ?>
    <div class="admin-card"><p><?php echo e('処理が完了しました: ' . $ok); ?></p></div>
<?php endif; ?>
<?php if ($error !== '') : ?>
    <div class="admin-card"><p><?php echo e($error); ?></p></div>
<?php endif; ?>
<?php if (!$hasTable) : ?>
    <div class="admin-card"><p>mutual_links テーブルが未作成のため、相互リンク管理は利用できません。</p></div>
<?php else : ?>
    <div class="admin-card">
        <h2><?php echo $editId > 0 ? '相互リンクを編集' : '相互リンクを新規追加'; ?></h2>
        <form method="post">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="<?php echo $editId > 0 ? 'update' : 'create'; ?>">
            <?php if ($editId > 0) : ?>
                <input type="hidden" name="id" value="<?php echo e((string)$form['id']); ?>">
            <?php endif; ?>
            <label>タイトル</label>
            <input type="text" name="title" maxlength="200" required value="<?php echo e((string)$form['title']); ?>">
            <label>URL</label>
            <input type="url" name="url" required value="<?php echo e((string)$form['url']); ?>" placeholder="https://example.com/">
            <label>並び順</label>
            <input type="number" name="sort_order" value="<?php echo e((string)$form['sort_order']); ?>">
            <label><input type="checkbox" name="is_enabled" value="1" <?php echo ((string)$form['is_enabled'] === '1') ? 'checked' : ''; ?>> 有効にする</label>
            <button type="submit"><?php echo $editId > 0 ? '更新する' : '追加する'; ?></button>
            <?php if ($editId > 0) : ?>
                <a href="<?php echo e(admin_url('links.php')); ?>">新規追加に戻る</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="admin-card">
        <h2>登録済みリンク一覧</h2>
        <?php if ($rows === []) : ?>
            <p>リンクはまだありません。上のフォームから新規追加してください。</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>タイトル</th>
                        <th>URL</th>
                        <th>並び順</th>
                        <th>有効</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r) : ?>
                        <tr>
                            <td><?php echo e((string)$r['id']); ?></td>
                            <td><?php echo e((string)($r['site_name'] ?? '')); ?></td>
                            <td><a href="<?php echo e((string)($r['site_url'] ?? '')); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)($r['site_url'] ?? '')); ?></a></td>
                            <td><?php echo e((string)($r['display_order'] ?? '0')); ?></td>
                            <td><?php echo ((int)($r['is_enabled'] ?? 0) === 1) ? 'ON' : 'OFF'; ?></td>
                            <td>
                                <a href="<?php echo e(admin_url('links.php?edit=' . (string)$r['id'])); ?>">編集</a>
                                <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？');">
                                    <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>">
                                    <button type="submit">削除</button>
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
$main = (string)ob_get_clean();
$content = $main;
include __DIR__ . '/../partials/admin_layout.php';
