<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../lib/site_settings.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/admin_auth.php';

$siteTitle = trim(site_title_setting(''));
if ($siteTitle === '') {
    $siteTitle = 'サイトタイトル未設定';
}
?>
<header class="admin-topbar">
    <div class="admin-topbar__left"><a href="<?php echo e(admin_url('index.php')); ?>"><?php echo e($siteTitle); ?> 管理</a></div>
    <div class="admin-topbar__right">
        <?php if (function_exists('admin_dev_auth_bypass_active') && admin_dev_auth_bypass_active()) : ?>
            <span class="admin-bypass-badge">開発用認証バイパス有効中</span>
        <?php endif; ?>
        <?php $currentAdmin = admin_current_user(); ?>
        <span>ログイン中: <?php echo e((string)($currentAdmin['username'] ?? '')); ?></span>
        <a href="<?php echo e(base_url() . '/index.php'); ?>" target="_blank" rel="noopener noreferrer">フロント表示</a>
        <span class="admin-topbar__separator" aria-hidden="true"> | </span>
        <form method="post" action="<?php echo e(admin_url('logout.php')); ?>"><input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>"><button type="submit">ログアウト</button></form>
    </div>
</header>
