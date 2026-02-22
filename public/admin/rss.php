<?php
declare(strict_types=1);

require_once __DIR__ . '/_page.php';

function rss_admin_validate_http_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function rss_admin_redirect(string $query = ''): void
{
    $url = admin_url('rss.php' . ($query !== '' ? '?' . $query : ''));
    header('Location: ' . $url);
    exit;
}

admin_render('RSS取得状況', static function (): void {
    admin_trace_push('page:start:rss.php');

    $error = '';
    $ok = (string)($_GET['ok'] ?? '');

    $hasSources = admin_table_exists('rss_sources');
    $hasItems = admin_table_exists('rss_items');
    $listError = '';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!admin_post_csrf_valid()) {
            $error = 'CSRFトークンが無効です。';
        } elseif (!$hasSources) {
            $error = 'rss_sources テーブルが見つからないため保存できません。';
        } else {
            $action = (string)($_POST['action'] ?? '');

            try {
                if ($action === 'create_source') {
                    $name = trim((string)($_POST['name'] ?? ''));
                    $feedUrl = trim((string)($_POST['feed_url'] ?? ''));

                    if ($name === '') {
                        $error = 'サイト名は必須です。';
                    } elseif (!rss_admin_validate_http_url($feedUrl)) {
                        $error = 'RSS URLは http(s):// 形式で入力してください。';
                    } else {
                        db()->prepare('INSERT INTO rss_sources (name, feed_url, is_enabled, last_fetched_at) VALUES (:name, :feed_url, 1, NULL)')
                            ->execute([':name' => $name, ':feed_url' => $feedUrl]);
                        rss_admin_redirect('ok=created');
                    }
                }

                if ($action === 'toggle_source') {
                    $id = (int)($_POST['id'] ?? 0);
                    $enabled = (int)($_POST['enabled'] ?? 0) === 1 ? 1 : 0;
                    if ($id <= 0) {
                        $error = '更新対象が不正です。';
                    } else {
                        db()->prepare('UPDATE rss_sources SET is_enabled = :enabled WHERE id = :id')
                            ->execute([':enabled' => $enabled, ':id' => $id]);
                        rss_admin_redirect('ok=toggled');
                    }
                }
            } catch (Throwable $exception) {
                error_log('rss.php action failed: ' . $exception->getMessage());
                $error = '保存処理に失敗しました。';
            }
        }
    }

    $sources = [];
    if ($hasSources) {
        try {
            $sources = db()->query('SELECT * FROM rss_sources ORDER BY id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('rss.php sources fetch failed: ' . $exception->getMessage());
            $listError = 'データ取得に失敗しました。';
        }
    }

    $itemsPerSource = [];
    if ($hasItems && $hasSources) {
        try {
            $rows = db()->query('SELECT source_id, COUNT(*) AS cnt FROM rss_items GROUP BY source_id')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $itemsPerSource[(int)($row['source_id'] ?? 0)] = (int)($row['cnt'] ?? 0);
            }
        } catch (Throwable $exception) {
            error_log('rss.php item count fetch failed: ' . $exception->getMessage());
            if ($listError === '') {
                $listError = 'データ取得に失敗しました。';
            }
        }
    }

    $okMessageMap = [
        'created' => 'RSSソースを追加しました。',
        'toggled' => 'RSSソースの有効/無効を更新しました。',
    ];
    $okMessage = $okMessageMap[$ok] ?? '';
    ?>
    <h1>RSS取得状況</h1>

    <?php if ($okMessage !== '') : ?>
        <div class="admin-card" style="background:#e7f5e7;padding:12px;margin-bottom:16px;"><p style="margin:0;">✓ <?php echo e($okMessage); ?></p></div>
    <?php endif; ?>

    <?php if ($error !== '') : ?>
        <div class="admin-card" style="background:#ffe7e7;padding:12px;margin-bottom:16px;"><p style="margin:0;color:#c00;">✗ <?php echo e($error); ?></p></div>
    <?php endif; ?>

    <?php if ($listError !== '') : ?>
        <div class="admin-card" style="background:#ffe7e7;padding:12px;margin-bottom:16px;"><p style="margin:0;color:#c00;">✗ <?php echo e($listError); ?></p></div>
    <?php endif; ?>

    <?php if (!$hasSources) : ?>
        <div class="admin-card" style="background:#fff3cd;padding:12px;margin-bottom:16px;"><p style="margin:0;">⚠ rss_sourcesテーブルが未作成です。`sql/schema.sql` を適用してください。</p></div>
    <?php endif; ?>

    <div class="admin-card" style="margin-bottom:20px;">
        <h2>RSSソース追加</h2>
        <form method="post" style="max-width:640px;">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="create_source">

            <label style="display:block;margin:0 0 8px;font-weight:bold;">サイト名 *</label>
            <input type="text" name="name" required style="width:100%;max-width:500px;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:12px;">

            <label style="display:block;margin:0 0 8px;font-weight:bold;">RSS URL *</label>
            <input type="url" name="feed_url" required placeholder="https://example.com/feed.xml" style="width:100%;max-width:500px;padding:6px;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;">

            <button type="submit" style="padding:8px 16px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;">追加</button>
        </form>
    </div>

    <?php if ($hasSources) : ?>
        <div class="admin-card">
            <h2>登録済みRSSソース (<?php echo e((string)count($sources)); ?>件)</h2>
            <?php if ($sources !== []) : ?>
                <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                    <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="border:1px solid #ddd;padding:8px;text-align:left;">ID</th>
                        <th style="border:1px solid #ddd;padding:8px;text-align:left;">name</th>
                        <th style="border:1px solid #ddd;padding:8px;text-align:left;">feed_url</th>
                        <th style="border:1px solid #ddd;padding:8px;text-align:center;">is_enabled</th>
                        <th style="border:1px solid #ddd;padding:8px;text-align:left;">last_fetched_at</th>
                        <th style="border:1px solid #ddd;padding:8px;text-align:right;">取得記事数</th>
                        <th style="border:1px solid #ddd;padding:8px;text-align:center;">操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sources as $row) : ?>
                        <?php
                        $id = (int)($row['id'] ?? 0);
                        $enabled = (int)($row['is_enabled'] ?? 1) === 1;
                        $itemCount = $itemsPerSource[$id] ?? 0;
                        ?>
                        <tr>
                            <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)$id); ?></td>
                            <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['name'] ?? '')); ?></td>
                            <td style="border:1px solid #ddd;padding:8px;"><a href="<?php echo e((string)($row['feed_url'] ?? '')); ?>" target="_blank" rel="noopener"><?php echo e((string)($row['feed_url'] ?? '')); ?></a></td>
                            <td style="border:1px solid #ddd;padding:8px;text-align:center;"><?php echo $enabled ? '1' : '0'; ?></td>
                            <td style="border:1px solid #ddd;padding:8px;"><?php echo e((string)($row['last_fetched_at'] ?? '')); ?></td>
                            <td style="border:1px solid #ddd;padding:8px;text-align:right;"><?php echo e((string)$itemCount); ?></td>
                            <td style="border:1px solid #ddd;padding:8px;">
                                <form method="post">
                                    <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="toggle_source">
                                    <input type="hidden" name="id" value="<?php echo e((string)$id); ?>">
                                    <input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
                                    <button type="submit" style="width:100%;"><?php echo $enabled ? '無効化' : '有効化'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>データなし</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
});
