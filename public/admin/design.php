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
            'design.logo_url' => trim((string)($_POST['logo_url'] ?? '')),
            'design.ogp_image_url' => trim((string)($_POST['ogp_image_url'] ?? '')),
        ]);
        admin_flash_set('ok', 'デザイン設定を保存しました。');
        header('Location: ' . admin_url('design.php'));
        exit;
    }
}

$ok = admin_flash_get('ok');
$logoUrl = site_setting_get('design.logo_url', '');
$ogpUrl = site_setting_get('design.ogp_image_url', '');
$pageTitle = 'デザイン設定';
ob_start();
?>
<h1>デザイン設定</h1>
<?php if ($ok !== '') : ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<?php if ($error !== '') : ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card">
    <form method="post">
        <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
        <label>ロゴ画像URL</label>
        <input type="url" name="logo_url" value="<?php echo e($logoUrl); ?>">
        <?php if ($logoUrl !== '') : ?><p><img src="<?php echo e($logoUrl); ?>" alt="logo preview" style="max-width:240px;height:auto;"></p><?php endif; ?>
        <label>OGP画像URL</label>
        <input type="url" name="ogp_image_url" value="<?php echo e($ogpUrl); ?>">
        <?php if ($ogpUrl !== '') : ?><p><img src="<?php echo e($ogpUrl); ?>" alt="ogp preview" style="max-width:240px;height:auto;"></p><?php endif; ?>
        <button type="submit">保存</button>
    </form>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
