<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../admin/menu.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$currentStubPage = basename((string)($_GET['page'] ?? ''));

$groups = admin_menu_groups();
?>
<aside class="admin-sidebar" aria-label="管理メニュー">
    <nav>
        <?php foreach ($groups as $group) : ?>
            <p class="admin-sidebar__heading"><?php echo e((string)($group['heading'] ?? '')); ?></p>
            <ul class="admin-sidebar__list">
                <?php foreach ((array)($group['items'] ?? []) as $item) :
                    $file = (string)($item['file'] ?? '');
                    $label = (string)($item['label'] ?? '');
                    $status = (string)($item['status'] ?? 'ready');

                    $href = $status === 'coming_soon'
                        ? admin_url('stub.php?page=' . rawurlencode($file))
                        : admin_url($file);

                    $isActive = $status === 'coming_soon'
                        ? ($currentScript === 'stub.php' && $currentStubPage === $file)
                        : ($currentScript === $file);
                    ?>
                    <li class="admin-sidebar__item">
                        <?php if ($file === '') : ?>
                            <span class="admin-menu__disabled">（未設定）</span>
                        <?php else : ?>
                            <a class="admin-menu__link <?php echo $isActive ? 'is-active' : ''; ?>"
                               href="<?php echo e($href); ?>">
                                <?php echo e($label); ?>
                                <?php if ($status === 'coming_soon') : ?><small>（準備中）</small><?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>
</aside>
