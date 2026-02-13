<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../lib/site_settings.php';

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error = 'CSRFトークンが無効です。';
    } else {
        site_setting_set_many([
            'site.name' => trim((string)($_POST['site_name'] ?? '')),
            'site.base_url' => trim((string)($_POST['base_url'] ?? '')),
            'site.notification_email' => trim((string)($_POST['notification_email'] ?? '')),
        ]);
        admin_flash_set('ok', 'サイト設定を保存しました。');
        header('Location: ' . admin_url('settings_site.php'));
        exit;
    }
}

$ok = admin_flash_get('ok');
$siteName = site_setting_get('site.name', '');
$baseUrlOverride = site_setting_get('site.base_url', '');
$autoBaseUrl = detect_base_url();
$notificationEmail = site_setting_get('site.notification_email', '');
$currentUserEmail = '';
$currentUserId = admin_current_user_id();
if ($currentUserId !== null) {
    $st = db()->prepare('SELECT email FROM admin_users WHERE id=:id LIMIT 1');
    $st->execute([':id' => $currentUserId]);
    $currentUserEmail = (string)($st->fetchColumn() ?: '');
}

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
        <input type="text" name="site_name" value="<?php echo e($siteName); ?>">

        <label>サイトURL（手動上書き）</label>
        <input type="url" name="base_url" value="<?php echo e($baseUrlOverride); ?>" placeholder="未入力時は自動検出">
        <p>自動検出URL: <?php echo e($autoBaseUrl); ?></p>

        <label>通知先メール（未入力時はログイン中管理者メールを利用）</label>
        <input type="email" name="notification_email" value="<?php echo e($notificationEmail); ?>">
        <p>候補: <?php echo e($currentUserEmail !== '' ? $currentUserEmail : '未検証'); ?></p>

        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
