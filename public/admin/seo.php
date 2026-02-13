<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_post_csrf_valid()) {
    $pairs = [
        'seo.meta_title' => trim((string)($_POST['meta_title'] ?? '')),
        'seo.meta_description' => trim((string)($_POST['meta_description'] ?? '')),
        'seo.noindex_admin' => isset($_POST['noindex_admin']) ? '1' : '0',
    ];
    foreach ($pairs as $k => $v) {
        db()->prepare('INSERT INTO site_settings(setting_key, setting_value, updated_at, created_at) VALUES (:k,:v,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()')->execute([':k'=>$k,':v'=>$v]);
    }
    admin_flash_set('ok','SEO設定を保存しました。');
    header('Location: ' . admin_url('seo.php'));exit;
}
$settings = db()->query("SELECT setting_key,setting_value FROM site_settings WHERE setting_key LIKE 'seo.%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$ok=admin_flash_get('ok');
$pageTitle='sitemap / robots / SEO'; ob_start(); ?>
<h1>SEO管理</h1><?php if($ok!==''): ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<div class="admin-card"><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><label>サイトMETAタイトル</label><input name="meta_title" value="<?php echo e((string)($settings['seo.meta_title'] ?? '')); ?>"><label>サイトMETA説明</label><textarea name="meta_description" rows="4"><?php echo e((string)($settings['seo.meta_description'] ?? '')); ?></textarea><label><input type="checkbox" name="noindex_admin" value="1" <?php echo ((string)($settings['seo.noindex_admin'] ?? '1')==='1')?'checked':''; ?>> admin配下をrobots拒否</label><button>保存</button></form></div>
<div class="admin-card"><p><a href="<?php echo e(base_url() . '/sitemap.php'); ?>" target="_blank" rel="noopener">動的 sitemap</a> / <a href="<?php echo e(base_url() . '/robots.php'); ?>" target="_blank" rel="noopener">動的 robots</a></p></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
