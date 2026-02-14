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
$content = static function () use ($ok, $error, $hasTable, $hasDisplayOrder, $hasIsEnabled, $rows, $form): void {
    $okMessageMap = [
        'created' => '相互リンクを作成しました。',
        'updated' => '相互リンクを更新しました。',
        'deleted' => '相互リンクを削除しました。',
    ];
    $okMessage = $okMessageMap[$ok] ?? '';
    $isCreate = (int)$form['id'] === 0;
    ?>
    <h1>相互リンク管理</h1>
    <?php if ($okMessage !== '') : ?>
        <div class="admin-card" style="background:#e7f5e7;padding:12px;margin-bottom:16px;">
            <p style="margin:0;">✓ <?php echo e($okMessage); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($error !== '') : ?>
        <div class="admin-card" style="background:#ffe7e7;padding:12px;margin-bottom:16px;">
            <p style="margin:0;color:#c00;">✗ <?php echo e($error); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$hasTable) : ?>
        <div class="admin-card" style="background:#fff3cd;padding:12px;margin-bottom:16px;">
            <p style="margin:0;">⚠ mutual_linksテーブルが未作成です。データベース初期化を実行してください。</p>
        </div>
    <?php endif; ?>

    <div class="admin-card" style="margin-bottom:24px;">
        <h2><?php echo $isCreate ? '相互リンク新規作成' : '相互リンク編集'; ?></h2>
        <form method="post" style="max-width:600px;">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="<?php echo $isCreate ? 'create' : 'update'; ?>">
            <input type="hidden" name="id" value="<?php echo e((string)$form['id']); ?>">

            <label style="display:block;margin-bottom:8px;font-weight:bold;">サイト名 <span style="color:#c00;">*</span></label>
            <input type="text" name="title" value="<?php echo e((string)$form['title']); ?>" required placeholder="例：PinkClub" style="width:100%;max-width:500px;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;">

            <label style="display:block;margin-bottom:8px;font-weight:bold;">URL <span style="color:#c00;">*</span></label>
            <input type="url" name="url" value="<?php echo e((string)$form['url']); ?>" required placeholder="https://example.com" style="width:100%;max-width:500px;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;">

            <?php if ($hasDisplayOrder) : ?>
                <label style="display:block;margin-bottom:8px;font-weight:bold;">表示順序</label>
                <input type="number" name="sort_order" value="<?php echo e((string)$form['sort_order']); ?>" placeholder="100" min="0" max="9999" style="width:120px;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;">
            <?php endif; ?>

            <?php if ($hasIsEnabled) : ?>
                <label style="display:block;margin-bottom:16px;">
                    <input type="checkbox" name="is_enabled" value="1" <?php echo ($form['is_enabled'] === '1') ? 'checked' : ''; ?>>
                    有効にする
                </label>
            <?php endif; ?>

            <div style="margin-top:16px;">
                <button type="submit" style="padding:8px 16px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;">
                    <?php echo $isCreate ? '追加' : '更新'; ?>
                </button>
                <?php if (!$isCreate) : ?>
                    <a href="<?php echo e(admin_url('links.php')); ?>" style="margin-left:8px;padding:8px 16px;text-decoration:none;color:#666;">キャンセル</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($hasTable) : ?>
        <div class="admin-card">
            <h2>登録済み相互リンク (<?php echo e((string)count($rows)); ?>件)</h2>
            <?php if (count($rows) > 0) : ?>
                <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="border:1px solid #ddd;padding:8px;text-align:left;">ID</th>
                            <th style="border:1px solid #ddd;padding:8px;text-align:left;">サイト名</th>
                            <th style="border:1px solid #ddd;padding:8px;text-align:left;">URL</th>
                            <?php if ($hasDisplayOrder) : ?>
                                <th style="border:1px solid #ddd;padding:8px;text-align:center;width:80px;">順序</th>
                            <?php endif; ?>
                            <?php if ($hasIsEnabled) : ?>
                                <th style="border:1px solid #ddd;padding:8px;text-align:center;width:80px;">状態</th>
                            <?php endif; ?>
                            <th style="border:1px solid #ddd;padding:8px;text-align:center;width:120px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)$row['id']); ?></td>
                                <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['site_name'] ?? '')); ?></td>
                                <td style="border:1px solid #ddd;padding:8px;">
                                    <?php
                                    $url = (string)($row['site_url'] ?? '');
                                    $displayUrl = mb_strlen($url) > 50 ? mb_substr($url, 0, 47) . '...' : $url;
                                    ?>
                                    <a href="<?php echo e($url); ?>" target="_blank" rel="noopener noreferrer" style="color:#2271b1;text-decoration:none;">
                                        <?php echo e($displayUrl); ?>
                                    </a>
                                </td>
                                <?php if ($hasDisplayOrder) : ?>
                                    <td style="border:1px solid #ddd;padding:8px;text-align:center;"><?php echo e((string)($row['display_order'] ?? '100')); ?></td>
                                <?php endif; ?>
                                <?php if ($hasIsEnabled) : ?>
                                    <td style="border:1px solid #ddd;padding:8px;text-align:center;">
                                        <?php echo ((int)($row['is_enabled'] ?? 1) === 1) ? '有効' : '無効'; ?>
                                    </td>
                                <?php endif; ?>
                                <td style="border:1px solid #ddd;padding:8px;text-align:center;">
                                    <a href="<?php echo e(admin_url('links.php?edit=' . (string)$row['id'])); ?>" style="color:#2271b1;text-decoration:none;margin-right:8px;">編集</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');">
                                        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo e((string)$row['id']); ?>">
                                        <button type="submit" style="background:none;border:none;color:#d63638;cursor:pointer;padding:0;text-decoration:underline;">削除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>相互リンクはまだありません。上のフォームから新規作成してください。</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
};
include __DIR__ . '/../partials/admin_layout.php';