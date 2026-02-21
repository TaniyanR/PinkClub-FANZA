<?php
declare(strict_types=1);

require_once __DIR__ . '/_page.php';

function links_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1'
    );
    $stmt->execute([':table' => $table, ':column' => $column]);
    return $stmt->fetchColumn() !== false;
}

function links_validate_http_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function links_safe_redirect(string $query = ''): void
{
    $url = admin_url('links.php' . ($query !== '' ? '?' . $query : ''));
    header('Location: ' . $url);
    exit;
}

function links_store_create_old_input(array $input): void
{
    admin_flash_set('links_create_old', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function links_redirect_with_error(string $message, array $input = []): void
{
    if ($input !== []) {
        links_store_create_old_input($input);
    }
    admin_flash_set('links_error', $message);
    links_safe_redirect();
}

admin_render('相互リンク管理', static function (): void {
    admin_trace_push('page:start:links.php');

    $error = admin_flash_get('links_error');
    $ok = admin_flash_get('links_ok');
    if ($ok === '') {
        $ok = (string)($_GET['ok'] ?? '');
    }
    $isDebug = (string)($_GET['debug'] ?? '') === '1';
    $debugInfo = ['db_name' => '', 'approved_enabled_count' => '0', 'error' => ''];
    $formInput = [
        'site_name' => '',
        'site_url' => '',
        'link_url' => '',
    ];
    $oldJson = admin_flash_get('links_create_old');
    if ($oldJson !== '') {
        $decoded = json_decode($oldJson, true);
        if (is_array($decoded)) {
            $formInput = array_merge($formInput, [
                'site_name' => trim((string)($decoded['site_name'] ?? '')),
                'site_url' => trim((string)($decoded['site_url'] ?? '')),
                'link_url' => trim((string)($decoded['link_url'] ?? '')),
            ]);
        }
    }

    $hasTable = admin_table_exists('mutual_links');
    $hasStatus = $hasTable && links_column_exists('mutual_links', 'status');
    $hasIsEnabled = $hasTable && links_column_exists('mutual_links', 'is_enabled');
    $hasDisplayOrder = $hasTable && links_column_exists('mutual_links', 'display_order');
    $hasApprovedAt = $hasTable && links_column_exists('mutual_links', 'approved_at');
    $hasCreatedAt = $hasTable && links_column_exists('mutual_links', 'created_at');
    $hasUpdatedAt = $hasTable && links_column_exists('mutual_links', 'updated_at');

    if ($isDebug) {
        try {
            $debugStmt = db()->query("SELECT DATABASE() AS db_name, COUNT(*) AS approved_enabled_count FROM mutual_links WHERE status='approved' AND is_enabled=1");
            $debugRow = $debugStmt ? $debugStmt->fetch(PDO::FETCH_ASSOC) : null;
            if (is_array($debugRow)) {
                $debugInfo['db_name'] = (string)($debugRow['db_name'] ?? '');
                $debugInfo['approved_enabled_count'] = (string)($debugRow['approved_enabled_count'] ?? '0');
            }
        } catch (Throwable $e) {
            $debugInfo['error'] = $e->getMessage();
        }
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!admin_post_csrf_valid()) {
            links_redirect_with_error('CSRFトークンが無効です。', $formInput);
        } elseif (!$hasTable) {
            links_redirect_with_error('mutual_links テーブルが見つからないため保存できません。', $formInput);
        } else {
            $action = (string)($_POST['action'] ?? '');
            $formInput = [
                'site_name' => trim((string)($_POST['site_name'] ?? '')),
                'site_url' => trim((string)($_POST['site_url'] ?? '')),
                'link_url' => trim((string)($_POST['link_url'] ?? '')),
            ];

            try {
            if ($action === 'create') {
                $siteName = trim((string)($_POST['site_name'] ?? ''));
                $siteUrl = trim((string)($_POST['site_url'] ?? ''));
                $linkUrl = trim((string)($_POST['link_url'] ?? ''));

                if ($siteName === '') {
                    links_redirect_with_error('サイト名は必須です。', $formInput);
                } elseif (!links_validate_http_url($siteUrl)) {
                    links_redirect_with_error('サイトURLは http(s):// 形式で入力してください。', $formInput);
                } elseif (!links_validate_http_url($linkUrl)) {
                    links_redirect_with_error('リンクURLは http(s):// 形式で入力してください。', $formInput);
                } else {
                    $columns = ['site_name', 'site_url', 'link_url'];
                    $values = [':site_name', ':site_url', ':link_url'];
                    $params = [
                        ':site_name' => $siteName,
                        ':site_url' => $siteUrl,
                        ':link_url' => $linkUrl,
                    ];

                    if ($hasStatus) {
                        $columns[] = 'status';
                        $values[] = "'pending'";
                    }
                    if ($hasIsEnabled) {
                        $columns[] = 'is_enabled';
                        $values[] = '1';
                    }
                    if ($hasDisplayOrder) {
                        $columns[] = 'display_order';
                        $values[] = '100';
                    }
                    if ($hasCreatedAt) {
                        $columns[] = 'created_at';
                        $values[] = 'NOW()';
                    }
                    if ($hasUpdatedAt) {
                        $columns[] = 'updated_at';
                        $values[] = 'NOW()';
                    }

                    $sql = sprintf(
                        'INSERT INTO mutual_links (%s) VALUES (%s)',
                        implode(', ', $columns),
                        implode(', ', $values)
                    );
                    db()->prepare($sql)->execute($params);
                    admin_flash_set('links_ok', 'created');
                    links_safe_redirect();
                }
            }

            if ($action === 'change_status') {
                $id = (int)($_POST['id'] ?? 0);
                $next = (string)($_POST['next_status'] ?? '');
                if ($id <= 0 || !in_array($next, ['approved', 'rejected'], true)) {
                    $error = 'ステータス更新対象が不正です。';
                } else {
                    $sets = [];
                    if ($hasStatus) {
                        $sets[] = 'status = :status';
                    }
                    if ($hasApprovedAt) {
                        if ($next === 'approved') {
                            $sets[] = 'approved_at = NOW()';
                        } else {
                            $sets[] = 'approved_at = NULL';
                        }
                    }
                    if ($hasUpdatedAt) {
                        $sets[] = 'updated_at = NOW()';
                    }

                    if ($sets !== []) {
                        $params = [':id' => $id];
                        if ($hasStatus) {
                            $params[':status'] = $next;
                        }
                        $sql = 'UPDATE mutual_links SET ' . implode(', ', $sets) . ' WHERE id = :id';
                        db()->prepare($sql)->execute($params);
                    }
                    links_safe_redirect('ok=status');
                }
            }

            if ($action === 'toggle_enabled') {
                $id = (int)($_POST['id'] ?? 0);
                $enabled = (int)($_POST['enabled'] ?? 0) === 1 ? 1 : 0;
                if ($id <= 0 || !$hasIsEnabled) {
                    $error = '有効/無効の更新対象が不正です。';
                } else {
                    $sets = ['is_enabled = :enabled'];
                    if ($hasUpdatedAt) {
                        $sets[] = 'updated_at = NOW()';
                    }

                    $sql = 'UPDATE mutual_links SET ' . implode(', ', $sets) . ' WHERE id = :id';
                    db()->prepare($sql)->execute([
                        ':id' => $id,
                        ':enabled' => $enabled,
                    ]);
                    links_safe_redirect('ok=enabled');
                }
            }

            if ($action === 'save_display_order') {
                $orders = $_POST['display_order'] ?? [];
                if (!is_array($orders) || !$hasDisplayOrder) {
                    $error = '表示順の更新対象が不正です。';
                } else {
                    $stmt = db()->prepare('UPDATE mutual_links SET display_order = :display_order WHERE id = :id');
                    foreach ($orders as $id => $order) {
                        $rowId = (int)$id;
                        if ($rowId <= 0) {
                            continue;
                        }
                        $displayOrder = filter_var($order, FILTER_VALIDATE_INT);
                        $displayOrder = $displayOrder === false ? 100 : (int)$displayOrder;
                        $stmt->execute([':id' => $rowId, ':display_order' => $displayOrder]);
                    }
                    links_safe_redirect('ok=order');
                }
            }
            } catch (Throwable $e) {
                error_log('links.php action failed: ' . $e->getMessage());
                links_redirect_with_error('保存処理に失敗しました。時間をおいて再度お試しください。', $formInput);
            }
        }
    }

    $rows = [];
    if ($hasTable) {
        $orderBy = $hasDisplayOrder ? 'display_order ASC, id ASC' : 'id ASC';
        $rows = db()->query('SELECT * FROM mutual_links ORDER BY ' . $orderBy . ' LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
    }

    $okMessageMap = [
        'created' => '相互リンクを追加しました（pending）。',
        'status' => 'ステータスを更新しました。',
        'enabled' => '有効/無効を更新しました。',
        'order' => '表示順を保存しました。',
    ];
    $okMessage = $okMessageMap[$ok] ?? '';
    $statusLabels = [
        'pending' => '承認待ち',
        'approved' => '承認済み',
        'rejected' => '却下',
        'disabled' => '無効',
    ];
    ?>
    <h1>相互リンク管理</h1>

    <?php if ($okMessage !== '') : ?>
        <div class="admin-card" style="background:#e7f5e7;padding:12px;margin-bottom:16px;"><p style="margin:0;">✓ <?php echo e($okMessage); ?></p></div>
    <?php endif; ?>

    <?php if ($error !== '') : ?>
        <div class="admin-card" style="background:#ffe7e7;padding:12px;margin-bottom:16px;"><p style="margin:0;color:#c00;">✗ <?php echo e($error); ?></p></div>
    <?php endif; ?>

    <?php if ($isDebug) : ?>
        <div class="admin-card" style="background:#eef6ff;padding:12px;margin-bottom:16px;">
            <p style="margin:0 0 6px;"><strong>Debug</strong></p>
            <p style="margin:0;">接続DB: <?php echo e($debugInfo['db_name']); ?></p>
            <p style="margin:0;">mutual_links (status='approved' AND is_enabled=1): <?php echo e($debugInfo['approved_enabled_count']); ?></p>
            <?php if ($debugInfo['error'] !== '') : ?>
                <p style="margin:6px 0 0;color:#b71c1c;">エラー: <?php echo e($debugInfo['error']); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$hasTable) : ?>
        <div class="admin-card" style="background:#fff3cd;padding:12px;margin-bottom:16px;"><p style="margin:0;">⚠ mutual_linksテーブルが未作成です。</p></div>
    <?php endif; ?>

    <div class="admin-card" style="margin-bottom:20px;">
        <h2>新規追加</h2>
        <form method="post" style="max-width:640px;">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="create">

            <label style="display:block;margin:0 0 8px;font-weight:bold;">サイト名 *</label>
            <input type="text" name="site_name" required value="<?php echo e($formInput['site_name']); ?>" style="width:100%;max-width:500px;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:12px;">

            <label style="display:block;margin:0 0 8px;font-weight:bold;">サイトURL *</label>
            <input type="url" name="site_url" required value="<?php echo e($formInput['site_url']); ?>" placeholder="https://example.com" style="width:100%;max-width:500px;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:12px;">

            <label style="display:block;margin:0 0 8px;font-weight:bold;">リンクURL *</label>
            <input type="url" name="link_url" required value="<?php echo e($formInput['link_url']); ?>" placeholder="https://example.com" style="width:100%;max-width:500px;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;">

            <button type="submit" style="padding:8px 16px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;">追加</button>
        </form>
    </div>

    <?php if ($hasTable) : ?>
        <div class="admin-card">
            <h2>登録済み相互リンク (<?php echo e((string)count($rows)); ?>件)</h2>
            <?php if ($rows !== []) : ?>
                <form method="post">
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_display_order">
                    <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                        <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="border:1px solid #ddd;padding:8px;text-align:left;">ID</th>
                            <th style="border:1px solid #ddd;padding:8px;text-align:left;">サイト名</th>
                            <th style="border:1px solid #ddd;padding:8px;text-align:left;">自サイトURL</th>
                            <th style="border:1px solid #ddd;padding:8px;text-align:left;">相手URL</th>
                            <?php if ($hasStatus) : ?><th style="border:1px solid #ddd;padding:8px;text-align:left;">状態</th><?php endif; ?>
                            <?php if ($hasIsEnabled) : ?><th style="border:1px solid #ddd;padding:8px;text-align:center;">有効</th><?php endif; ?>
                            <?php if ($hasDisplayOrder) : ?><th style="border:1px solid #ddd;padding:8px;text-align:center;">表示順</th><?php endif; ?>
                            <?php if ($hasApprovedAt) : ?><th style="border:1px solid #ddd;padding:8px;text-align:left;">承認日時</th><?php endif; ?>
                            <?php if ($hasCreatedAt) : ?><th style="border:1px solid #ddd;padding:8px;text-align:left;">作成日時</th><?php endif; ?>
                            <?php if ($hasUpdatedAt) : ?><th style="border:1px solid #ddd;padding:8px;text-align:left;">更新日時</th><?php endif; ?>
                            <th style="border:1px solid #ddd;padding:8px;text-align:center;">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <?php
                            $id = (int)($row['id'] ?? 0);
                            $status = (string)($row['status'] ?? 'pending');
                            $statusLabel = $statusLabels[$status] ?? $status;
                            $enabled = (int)($row['is_enabled'] ?? 1) === 1;
                            ?>
                            <tr>
                                <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)$id); ?></td>
                                <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['site_name'] ?? '')); ?></td>
                                <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['site_url'] ?? '')); ?></td>
                                <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['link_url'] ?? '')); ?></td>
                                <?php if ($hasStatus) : ?><td style="border:1px solid #ddd;padding:8px;"><?php echo e($statusLabel); ?></td><?php endif; ?>
                                <?php if ($hasIsEnabled) : ?><td style="border:1px solid #ddd;padding:8px;text-align:center;"><?php echo $enabled ? '有効' : '無効'; ?></td><?php endif; ?>
                                <?php if ($hasDisplayOrder) : ?>
                                    <td style="border:1px solid #ddd;padding:8px;text-align:center;">
                                        <input type="number" name="display_order[<?php echo e((string)$id); ?>]" value="<?php echo e((string)($row['display_order'] ?? 100)); ?>" style="width:90px;">
                                    </td>
                                <?php endif; ?>
                                <?php if ($hasApprovedAt) : ?><td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['approved_at'] ?? '')); ?></td><?php endif; ?>
                                <?php if ($hasCreatedAt) : ?><td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['created_at'] ?? '')); ?></td><?php endif; ?>
                                <?php if ($hasUpdatedAt) : ?><td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['updated_at'] ?? '')); ?></td><?php endif; ?>
                                <td style="border:1px solid #ddd;padding:8px;">
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <?php if ($hasStatus) : ?>
                                            <form method="post">
                                                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="change_status">
                                                <input type="hidden" name="id" value="<?php echo e((string)$id); ?>">
                                                <input type="hidden" name="next_status" value="approved">
                                                <button type="submit" style="width:100%;">承認</button>
                                            </form>
                                            <form method="post">
                                                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="change_status">
                                                <input type="hidden" name="id" value="<?php echo e((string)$id); ?>">
                                                <input type="hidden" name="next_status" value="rejected">
                                                <button type="submit" style="width:100%;">却下</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($hasIsEnabled) : ?>
                                            <form method="post">
                                                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="toggle_enabled">
                                                <input type="hidden" name="id" value="<?php echo e((string)$id); ?>">
                                                <input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
                                                <button type="submit" style="width:100%;"><?php echo $enabled ? '無効化' : '有効化'; ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($hasDisplayOrder) : ?>
                        <div style="margin-top:12px;"><button type="submit" style="padding:8px 16px;">表示順を保存</button></div>
                    <?php endif; ?>
                </form>
            <?php else : ?>
                <p>相互リンクはまだありません。</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
});
