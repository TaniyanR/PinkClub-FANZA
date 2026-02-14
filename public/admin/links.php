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
admin_render($pageTitle, static function () use ($ok, $error, $hasTable, $rows): void {
    ?>
    <h1>相互リンク管理（最小表示）</h1>
    <div class="admin-card">
        <p>admin_render には到達しています。</p>
        <?php if ($ok !== '') : ?><p><?php echo e('処理が完了しました: ' . $ok); ?></p><?php endif; ?>
        <?php if ($error !== '') : ?><p><?php echo e($error); ?></p><?php endif; ?>
        <p><?php echo $hasTable ? 'mutual_links テーブル: あり' : 'mutual_links テーブル: なし'; ?></p>
        <p><?php echo e('取得件数: ' . (string)count($rows)); ?></p>
    </div>
    <?php
});
