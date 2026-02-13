<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$slots = ['pc_header_right','pc_sidebar','pc_content_top','pc_content_bottom','sp_header','sp_footer'];
$error='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error='CSRFトークンが無効です。';
    } else {
        foreach ($slots as $slot) {
            $code = (string)($_POST['slot_'.$slot] ?? '');
            db()->prepare('INSERT INTO code_snippets(slot_key, snippet_html, is_enabled, updated_at, created_at) VALUES (:k,:h,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE snippet_html=VALUES(snippet_html),updated_at=NOW()')
                ->execute([':k'=>$slot, ':h'=>$code]);
        }
        admin_flash_set('ok','広告コードを保存しました。');
        header('Location: '.admin_url('ads.php'));exit;
    }
}
$rows = db()->query('SELECT slot_key,snippet_html FROM code_snippets')->fetchAll(PDO::FETCH_KEY_PAIR);
$ok=admin_flash_get('ok');
$pageTitle='コード挿入 / 広告枠'; ob_start(); ?>
<h1>広告コード</h1><?php if($ok!==''): ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?><?php if($error!==''): ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card"><p>管理者のみ保存可能です。貼り付けコードは動作確認してから反映してください。</p>
<form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
<?php foreach($slots as $slot): ?><label><?php echo e($slot); ?></label><textarea name="slot_<?php echo e($slot); ?>" rows="4"><?php echo e((string)($rows[$slot] ?? '')); ?></textarea><?php endforeach; ?>
<button type="submit">保存</button></form></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
