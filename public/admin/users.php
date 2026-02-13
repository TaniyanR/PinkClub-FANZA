<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$error='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!admin_post_csrf_valid()) {
        $error='CSRFトークンが無効です。';
    } else {
        $action=(string)($_POST['action'] ?? '');
        if($action==='create'){
            $u=trim((string)$_POST['username']);$p=(string)$_POST['password'];
            if($u!=='' && strlen($p)>=8){
                db()->prepare('INSERT INTO admin_users(username,password_hash,role,is_active,created_at,updated_at) VALUES (:u,:p,"admin",1,NOW(),NOW())')->execute([':u'=>$u,':p'=>password_hash($p,PASSWORD_DEFAULT)]);
                admin_flash_set('ok','ユーザーを追加しました。');
            }
        } elseif($action==='toggle'){
            db()->prepare('UPDATE admin_users SET is_active = IF(is_active=1,0,1), updated_at=NOW() WHERE id=:id')->execute([':id'=>(int)$_POST['id']]);
            admin_flash_set('ok','状態を更新しました。');
        }
        header('Location: '.admin_url('users.php')); exit;
    }
}
$rows=db()->query('SELECT id,username,role,is_active,created_at FROM admin_users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
$ok=admin_flash_get('ok');
$pageTitle='アカウント設定'; ob_start(); ?>
<h1>アカウント設定</h1><?php if($ok!==''): ?><div class="admin-card"><p><?php echo e($ok); ?></p></div><?php endif; ?><?php if($error!==''): ?><div class="admin-card"><p><?php echo e($error); ?></p></div><?php endif; ?>
<div class="admin-card"><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="create"><label>ユーザー名</label><input name="username" required><label>パスワード(8文字以上)</label><input type="password" name="password" minlength="8" required><button>追加</button></form></div>
<div class="admin-card"><table class="admin-table"><thead><tr><th>ID</th><th>ユーザー名</th><th>ロール</th><th>有効</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?php echo e((string)$r['id']); ?></td><td><?php echo e((string)$r['username']); ?></td><td><?php echo e((string)$r['role']); ?></td><td><?php echo ((int)$r['is_active']===1)?'ON':'OFF'; ?></td><td><form method="post"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo e((string)$r['id']); ?>"><button>ON/OFF</button></form></td></tr><?php endforeach; ?><?php if($rows===[]): ?><tr><td colspan="5">ユーザーなし</td></tr><?php endif; ?></tbody></table></div>
<?php $content=(string)ob_get_clean(); include __DIR__.'/../partials/admin_layout.php';
