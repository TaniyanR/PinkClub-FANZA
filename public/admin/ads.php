<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
admin_trace_push('page:start:ads.php');
require_once __DIR__ . '/../partials/_helpers.php';

$slotLabels = [
    'header_left_728x90' => 'PC: ヘッダー左 (728x90)',
    'sidebar_bottom' => 'PC: サイド下',
    'content_top' => 'PC: 本文上',
    'content_bottom' => 'PC: 本文下',
    'sp_header_below' => 'SP: ヘッダー下',
    'sp_footer_above' => 'SP: フッター上',
];
$pageTypes = ['home' => 'トップ', 'list' => '一覧', 'item' => '詳細', 'page' => '固定ページ'];
$error = '';
$formValues = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        foreach (array_keys($slotLabels) as $slot) {
            $formValues[$slot] = [
                'snippet_html' => (string)($_POST['slot_' . $slot] ?? ''),
                'is_enabled' => isset($_POST['enabled_' . $slot]) ? 1 : 0,
            ];
        }

        try {
        $pdo = db();
        foreach (array_keys($slotLabels) as $slot) {
            $code = (string)($_POST['slot_' . $slot] ?? '');
            $enabled = isset($_POST['enabled_' . $slot]) ? 1 : 0;
            $pdo->prepare('INSERT INTO code_snippets(slot_key, snippet_html, is_enabled, updated_at, created_at) VALUES (:k,:h,:e,NOW(),NOW()) ON DUPLICATE KEY UPDATE snippet_html=VALUES(snippet_html),is_enabled=VALUES(is_enabled),updated_at=NOW()')
                ->execute([':k' => $slot, ':h' => $code, ':e' => $enabled]);
        }

        $rules = ad_default_display_rules();
        foreach (['pc', 'sp'] as $device) {
            foreach ($rules[$device] as $position => $positionRules) {
                foreach ($positionRules as $key => $_) {
                    $field = 'rule_' . $device . '_' . $position . '_' . $key;
                    $rules[$device][$position][$key] = isset($_POST[$field]);
                }
            }
        }
        app_setting_set_many([
            'ads_display_rules' => json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        admin_flash_set('ok', '広告コードと表示設定を保存しました。');
        header('Location: ' . admin_url('ads.php'));
        exit;
        } catch (Throwable $e) {
            error_log('ads.php save failed: ' . $e->getMessage());
            $error = '保存に失敗しました。時間をおいて再度お試しください。';
        }
    }
}

$stmt = db()->query('SELECT slot_key,snippet_html,is_enabled FROM code_snippets');
$snippetRows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
$rows = [];
foreach ($snippetRows as $row) {
    $rows[(string)$row['slot_key']] = $row;
}
$rules = ad_display_rules();
$ok = admin_flash_get('ok');
$pageTitle = 'コード挿入 / 広告枠';
ob_start();
?>
<h1>広告コード</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>

<div class="admin-card">
    <p>管理者のみ保存可能です。コード未設定またはOFFの広告はフロントに一切表示されません。</p>
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <h2>1) コード入力（PC4 + SP2）</h2>
        <?php foreach ($slotLabels as $slot => $label) : ?>
            <?php $current = $formValues[$slot] ?? ($rows[$slot] ?? ['snippet_html' => '', 'is_enabled' => 1]); ?>
            <label><?php echo e($label); ?> (<?php echo e($slot); ?>)</label>
            <p style="margin:4px 0;">状態: <?php echo trim((string)$current['snippet_html']) === '' ? '未設定' : '設定済み'; ?></p>
            <label><input type="checkbox" name="enabled_<?php echo e($slot); ?>" value="1" <?php echo ((int)($current['is_enabled'] ?? 1) === 1) ? 'checked' : ''; ?>> 有効化</label>
            <textarea name="slot_<?php echo e($slot); ?>" rows="5"><?php echo e((string)($current['snippet_html'] ?? '')); ?></textarea>
            <hr>
        <?php endforeach; ?>

        <h2>2) 表示先設定（ON/OFF）</h2>
        <table class="admin-table">
            <thead><tr><th>位置</th><th>home</th><th>list</th><th>item</th><th>page</th><th>all</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $device => $positions) : ?>
                <?php foreach ($positions as $position => $positionRules) : ?>
                    <tr>
                        <td><?php echo e($device . ' / ' . $position); ?></td>
                        <?php foreach ($pageTypes as $key => $label) : ?>
                            <td>
                                <?php if (array_key_exists($key, $positionRules)) : ?>
                                    <label><input type="checkbox" name="rule_<?php echo e($device . '_' . $position . '_' . $key); ?>" value="1" <?php echo ((bool)$positionRules[$key]) ? 'checked' : ''; ?>> <?php echo e($label); ?></label>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <?php if (array_key_exists('all', $positionRules)) : ?>
                                <label><input type="checkbox" name="rule_<?php echo e($device . '_' . $position . '_all'); ?>" value="1" <?php echo ((bool)$positionRules['all']) ? 'checked' : ''; ?>> 全ページ</label>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit">保存</button>
    </form>
</div>
<?php
$main = (string)ob_get_clean();
require_once __DIR__ . '/_page.php';
admin_render($pageTitle, static function () use ($main): void {
    echo $main;
});
