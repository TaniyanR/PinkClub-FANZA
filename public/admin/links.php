<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:links.php');

function links_validate_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

$pageTitle = '相互リンク管理';
$errors = [];
$messages = [];
$hasTable = admin_table_exists('mutual_links');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $errors[] = 'CSRFトークンが無効です。';
    } elseif (!$hasTable) {
        $errors[] = 'mutual_links テーブルが未作成のため保存できません。';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create') {
            $siteName = trim((string)($_POST['site_name'] ?? ''));
            $siteUrl = trim((string)($_POST['site_url'] ?? ''));
            $linkUrl = trim((string)($_POST['link_url'] ?? ''));

            if ($siteName === '' || mb_strlen($siteName) > 255) {
                $errors[] = 'サイト名は1〜255文字で入力してください。';
            }
            if (!links_validate_url($siteUrl)) {
                $errors[] = 'サイトURLは http(s):// 形式で入力してください。';
            }
            if (!links_validate_url($linkUrl)) {
                $errors[] = 'リンクURLは http(s):// 形式で入力してください。';
            }

            if ($errors === []) {
                db()->prepare('INSERT INTO mutual_links (site_name, site_url, link_url, status, is_enabled, display_order, approved_at, created_at, updated_at) VALUES (:site_name, :site_url, :link_url, :status, 1, 100, NULL, NOW(), NOW())')
                    ->execute([
                        ':site_name' => $siteName,
                        ':site_url' => $siteUrl,
                        ':link_url' => $linkUrl,
                        ':status' => 'pending',
                    ]);
                $messages[] = '相互リンクを追加しました。';
            }
        }

        if ($action === 'approve') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db()->prepare('UPDATE mutual_links SET status=:status, is_enabled=1, approved_at=NOW(), updated_at=NOW() WHERE id=:id')
                    ->execute([':status' => 'approved', ':id' => $id]);
                $messages[] = '承認しました。';
            } else {
                $errors[] = '承認対象が不正です。';
            }
        }

        if ($action === 'reject') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db()->prepare('UPDATE mutual_links SET status=:status, is_enabled=0, updated_at=NOW() WHERE id=:id')
                    ->execute([':status' => 'rejected', ':id' => $id]);
                $messages[] = '却下しました。';
            } else {
                $errors[] = '却下対象が不正です。';
            }
        }

        if ($action === 'disable') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                db()->prepare('UPDATE mutual_links SET is_enabled=0, updated_at=NOW() WHERE id=:id')
                    ->execute([':id' => $id]);
                $messages[] = '停止しました。';
            } else {
                $errors[] = '停止対象が不正です。';
            }
        }

        if ($action === 'update_order') {
            $id = (int)($_POST['id'] ?? 0);
            $displayOrder = filter_var($_POST['display_order'] ?? '100', FILTER_VALIDATE_INT);
            if ($id > 0 && $displayOrder !== false) {
                db()->prepare('UPDATE mutual_links SET display_order=:display_order, updated_at=NOW() WHERE id=:id')
                    ->execute([':display_order' => (int)$displayOrder, ':id' => $id]);
                $messages[] = '表示順を更新しました。';
            } else {
                $errors[] = '表示順の更新内容が不正です。';
            }
        }
    }
}

$rows = [];
if ($hasTable) {
    $rows = db()->query('SELECT id, status, site_name, site_url, link_url, display_order, is_enabled, approved_at FROM mutual_links ORDER BY display_order ASC, id ASC LIMIT 500')
        ->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($hasTable, $errors, $messages, $rows): void {
    ?>
    <h1>相互リンク管理</h1>

    <?php foreach ($messages as $message) : ?>
        <div class="admin-card"><p><?php echo e($message); ?></p></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error) : ?>
        <div class="admin-card"><p><?php echo e($error); ?></p></div>
    <?php endforeach; ?>

    <div class="admin-card">
        <h2>新規追加</h2>
        <?php if (!$hasTable) : ?>
            <p>mutual_links テーブルが存在しません。</p>
        <?php else : ?>
            <form method="post">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="create">
                <p><label>サイト名<br><input type="text" name="site_name" required maxlength="255"></label></p>
                <p><label>サイトURL<br><input type="url" name="site_url" required placeholder="https://example.com/"></label></p>
                <p><label>リンクURL<br><input type="url" name="link_url" required placeholder="https://example.com/links"></label></p>
                <p><button type="submit">追加</button></p>
            </form>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <h2>一覧</h2>
        <?php if ($rows === []) : ?>
            <p>データがありません。</p>
        <?php else : ?>
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>status</th>
                    <th>サイト名</th>
                    <th>URL</th>
                    <th>表示順</th>
                    <th>is_enabled</th>
                    <th>承認日時</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo e((string)$row['id']); ?></td>
                        <td><?php echo e((string)$row['status']); ?></td>
                        <td><?php echo e((string)$row['site_name']); ?></td>
                        <td><a href="<?php echo e((string)$row['site_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo e((string)$row['site_url']); ?></a></td>
                        <td>
                            <form method="post" style="display:flex;gap:8px;align-items:center;">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="update_order">
                                <input type="hidden" name="id" value="<?php echo e((string)$row['id']); ?>">
                                <input type="number" name="display_order" value="<?php echo e((string)$row['display_order']); ?>" style="width:90px;">
                                <button type="submit">更新</button>
                            </form>
                        </td>
                        <td><?php echo ((int)$row['is_enabled'] === 1) ? '1' : '0'; ?></td>
                        <td><?php echo e((string)($row['approved_at'] ?? '')); ?></td>
                        <td>
                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?php echo e((string)$row['id']); ?>">
                                <button type="submit">承認</button>
                            </form>
                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?php echo e((string)$row['id']); ?>">
                                <button type="submit">却下</button>
                            </form>
                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="disable">
                                <input type="hidden" name="id" value="<?php echo e((string)$row['id']); ?>">
                                <button type="submit">停止</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
});
