<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$groups = [];

try {
    if (function_exists('admin_trace_push')) {
        admin_trace_push('sidebar:menu:require:before');
    }
    require_once __DIR__ . '/../admin/menu.php';
    if (function_exists('admin_trace_push')) {
        admin_trace_push('sidebar:menu:require:after');
        admin_trace_push('sidebar:menu_groups:before');
    }

    $groups = admin_menu_groups();

    if (function_exists('admin_trace_push')) {
        admin_trace_push('sidebar:menu_groups:after');
    }
} catch (Throwable $exception) {
    if (function_exists('admin_trace_push')) {
        admin_trace_push('sidebar:menu:failed');
    }
    $groups = [];
}
?>
<aside class="admin-sidebar" aria-label="管理メニュー">
    <nav>
        <?php if ($groups === []) : ?>
            <p class="admin-sidebar__heading">メニューを表示できませんでした。</p>
        <?php endif; ?>
        <?php foreach ($groups as $group) : ?>
            <?php
            $heading = (string)($group['heading'] ?? '');
            $emphasisGroups = ['設定', 'リンク設定', 'アフィリエイト設定', '固定ページ'];
            $isEmphasisGroup = in_array($heading, $emphasisGroups, true);
            ?>
            <?php if (!((bool)($group['standalone'] ?? false))) : ?>
                <p class="admin-sidebar__heading <?php echo $isEmphasisGroup ? 'admin-sidebar__heading--emphasis' : ''; ?>"><?php echo e($heading); ?></p>
            <?php endif; ?>
            <ul class="admin-sidebar__list">
                <?php foreach ((array)($group['items'] ?? []) as $item) :
                    $file = (string)($item['file'] ?? '');
                    $label = (string)($item['label'] ?? '');
                    $href = $file !== '' ? admin_url($file) : '#';
                    $menuPath = $file !== '' ? (string)parse_url($file, PHP_URL_PATH) : '';
                    $menuScript = $menuPath !== '' ? basename($menuPath) : '';
                    $menuTab = $file !== '' ? (string)parse_url($file, PHP_URL_QUERY) : '';
                    parse_str($menuTab, $menuQuery);
                    $currentTab = (string)($_GET['tab'] ?? '');
                    $itemTab = isset($menuQuery['tab']) ? (string)$menuQuery['tab'] : '';
                    $isActive = $file !== '' && $currentScript === $menuScript && ($itemTab === '' || $itemTab === $currentTab);
                    ?>
                    <li class="admin-sidebar__item">
                        <a class="admin-menu__link <?php echo $isEmphasisGroup ? 'admin-menu__link--emphasis' : ''; ?> <?php echo $isActive ? 'is-active' : ''; ?>"
                           href="<?php echo e($href); ?>">
                            <?php echo e($label); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>
</aside>
