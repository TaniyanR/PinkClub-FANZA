<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $pairs = [
            'site.name' => trim((string)($_POST['site_name'] ?? '')),
            'site.base_url' => trim((string)($_POST['base_url'] ?? '')),
            'site.contact_email' => trim((string)($_POST['contact_email'] ?? '')),
            'site.show_rss' => isset($_POST['show_rss']) ? '1' : '0',
            'site.show_links' => isset($_POST['show_links']) ? '1' : '0',
            'site.show_mail' => isset($_POST['show_mail']) ? '1' : '0',
        ];

        foreach ($pairs as $key => $value) {
            db()->prepare('INSERT INTO site_settings(setting_key,setting_value,updated_at,created_at) VALUES (:k,:v,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()')
                ->execute([':k' => $key, ':v' => $value]);
        }

        admin_flash_set('ok', 'サイト設定を保存しました。');
        header('Location: ' . admin_url('settings_site.php'));
        exit;
    }
}

$settings = db()->query("SELECT setting_key,setting_value FROM site_settings WHERE setting_key LIKE 'site.%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$ok = admin_flash_get('ok');

$pageTitle = 'サイト設定';
ob_start();
?>
<h1>サイト設定</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">

        <label>サイト名</label>
        <input type="text" name="site_name" value="<?php echo e((string)($settings['site.name'] ?? 'PinkClub-FANZA')); ?>" required>

        <label>URL</label>
        <input type="url" name="base_url" value="<?php echo e((string)($settings['site.base_url'] ?? base_url())); ?>" required>

        <label>連絡先メール</label>
        <input type="email" name="contact_email" value="<?php echo e((string)($settings['site.contact_email'] ?? '')); ?>">

        <label><input type="checkbox" name="show_rss" value="1" <?php echo (($settings['site.show_rss'] ?? '1') === '1') ? 'checked' : ''; ?>> RSS表示</label>
        <label><input type="checkbox" name="show_links" value="1" <?php echo (($settings['site.show_links'] ?? '1') === '1') ? 'checked' : ''; ?>> 相互リンク表示</label>
        <label><input type="checkbox" name="show_mail" value="1" <?php echo (($settings['site.show_mail'] ?? '1') === '1') ? 'checked' : ''; ?>> メール導線表示</label>

        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
