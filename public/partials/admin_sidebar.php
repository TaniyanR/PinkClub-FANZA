<?php

declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$menuGroups = require __DIR__ . '/../admin/menu.php';
?>
<aside class="admin-sidebar" aria-label="管理メニュー">
    <nav>
        <?php foreach ($menuGroups as $groupLabel => $items) : ?>
            <h3><?php echo e((string)$groupLabel); ?></h3>
            <ul>
                <?php foreach ($items as $item) :
                    $isActive = $currentScript === (string)$item['file'];
                    ?>
                    <li>
                        <a class="<?php echo $isActive ? 'is-active' : ''; ?>" href="<?php echo e(admin_url((string)$item['file'])); ?>">
                            <?php echo e((string)$item['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>
</aside>
