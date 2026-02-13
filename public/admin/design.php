<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_post_csrf_valid()) {
    $pairs=[
        'design.logo_url'=>trim((string)($_POST['logo_url'] ?? '')),
        'design.theme_color'=>trim((string)($_POST['theme_color'] ?? '#ff4b90')),
        'design.custom_css'=>(string)($_POST['custom_css'] ?? ''),
        'design.show_ranking'=>isset($_POST['show_ranking'])?'1':'0',
    ];
    foreach($pairs as $k=>$v){db()->prepare('INSERT INTO site_settings(setting_key,setting_value,updated_at,created_at) VALUES (:k,:v,NOW(),NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()')->execute([':k'=>$k,':v'=>$v]);}
    admin_flash_set('ok','デザイン設定を保存しました。');header('Location: '.admin_url('design.php'));exit;
}
$s=db()->query("SELECT setting_key,setting_value FROM site_settings WHERE setting_key LIKE 'design.%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$ok=admin_flash_get('ok');
$pageTitle='デザイン設定'; ob_start(); ?>
<h1>デザイン設定</h1><?php if($ok!==''): ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?>
<div class="admin-card"><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><label>ロゴURL</label><input name="logo_url" value="<?php echo e((string)($s['design.logo_url'] ?? '')); ?>"><label>テーマカラー</label><input name="theme_color" value="<?php echo e((string)($s['design.theme_color'] ?? '#ff4b90')); ?>"><label>カスタムCSS</label><textarea name="custom_css" rows="8"><?php echo e((string)($s['design.custom_css'] ?? '')); ?></textarea><label><input type="checkbox" name="show_ranking" value="1" <?php echo ((string)($s['design.show_ranking'] ?? '1')==='1')?'checked':''; ?>> 人気ランキング表示</label><button>保存</button></form></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
