<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';

$siteTitle = (string)config_get('site.title', 'PinkClub-FANZA');
?>
<header class="admin-topbar">
    <div class="admin-topbar__left">
        <a href="<?php echo e(admin_url('index.php')); ?>"><?php echo e($siteTitle); ?> 管理</a>
    </div>
    <div class="admin-topbar__right">
        <a href="<?php echo e(base_url() . '/index.php'); ?>" target="_blank" rel="noopener noreferrer">フロント表示</a>
        <form method="post" action="<?php echo e(admin_url('logout.php')); ?>">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            <button type="submit">ログアウト</button>
        </form>
    </div>
</header>
