<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

$menuItems = [
    ['file' => 'settings.php', 'label' => '管理設定'],
    ['file' => 'db_init.php', 'label' => 'DB初期化'],
    ['file' => 'import_items.php', 'label' => 'インポート'],
    ['file' => 'change_password.php', 'label' => 'パスワード変更'],
];
?>
<aside class="admin-sidebar" aria-label="管理メニュー">
    <nav>
        <ul>
            <?php foreach ($menuItems as $item) : ?>
                <?php $isActive = $currentScript === $item['file']; ?>
                <li>
                    <a class="<?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo e(admin_url($item['file'])); ?>"><?php echo e($item['label']); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
