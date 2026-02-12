<?php
declare(strict_types=1);
?>
<nav class="nav-search">
    <div class="nav-search__inner">
        <div class="nav-search__menu">
            <ul>
                <li><a class="nav-link" href="<?php echo e(admin_url('settings.php')); ?>">管理設定</a></li>
                <li><a class="nav-link" href="<?php echo e(admin_url('db_init.php')); ?>">DB初期化</a></li>
                <li><a class="nav-link" href="<?php echo e(admin_url('import_items.php')); ?>">インポート</a></li>
                <li><a class="nav-link" href="<?php echo e(admin_url('change_password.php')); ?>">パスワード変更</a></li>
            </ul>
        </div>
        <?php if (admin_current_user() !== null) : ?>
            <form method="post" action="<?php echo e(admin_url('logout.php')); ?>">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
                <button type="submit">ログアウト</button>
            </form>
        <?php endif; ?>
    </div>
</nav>
