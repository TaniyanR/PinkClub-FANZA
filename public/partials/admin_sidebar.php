<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../admin/menu.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$groups = admin_menu_groups();
?>
<aside class="admin-sidebar" aria-label="管理メニュー">
    <nav>
        <?php foreach ($groups as $group) : ?>
            <p class="admin-sidebar__heading"><?php echo e((string)$group['heading']); ?></p>
            <ul>
                <?php foreach ((array)$group['items'] as $item) :
                    $file = (string)($item['file'] ?? '');
                    $label = (string)($item['label'] ?? '');
                    $status = (string)($item['status'] ?? 'ready');
                    $isActive = $currentScript === $file;
                    ?>
                    <li>
                        <?php if ($status === 'coming_soon') : ?>
                            <span class="admin-menu__disabled"><?php echo e($label); ?></span>
                        <?php else : ?>
                            <a class="<?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo e(admin_url($file)); ?>"><?php echo e($label); ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>
</aside>
