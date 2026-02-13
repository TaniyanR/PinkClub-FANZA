<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../admin/menu.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$groups = admin_menu_groups();
?>
<aside class="admin-sidebar" aria-label="管理メニュー">
    <nav>
        <?php foreach ($groups as $group) : ?>
            <?php if (!((bool)($group['standalone'] ?? false))) : ?>
                <p class="admin-sidebar__heading"><?php echo e((string)($group['heading'] ?? '')); ?></p>
            <?php endif; ?>
            <ul class="admin-sidebar__list">
                <?php foreach ((array)($group['items'] ?? []) as $item) :
                    $file = (string)($item['file'] ?? '');
                    $label = (string)($item['label'] ?? '');
                    $href = $file !== '' ? admin_url($file) : '#';
                    $isActive = $file !== '' && ($currentScript === basename($file));
                    ?>
                    <li class="admin-sidebar__item">
                        <a class="admin-menu__link <?php echo $isActive ? 'is-active' : ''; ?>"
                           href="<?php echo e($href); ?>">
                            <?php echo e($label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>
</aside>
