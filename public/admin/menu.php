<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/admin_page_discovery.php';

function admin_menu_groups(): array
{
    $pages = admin_discover_pages();

    $frequent = [];
    $others = [];
    $legacy = [];
    $logout = [];

    foreach ($pages as $page) {
        $item = [
            'file' => $page['path'],
            'label' => $page['label'],
            'badge' => $page['broken'] ? '未整備' : '',
        ];

        $file = $page['file'];
        $isFrequent = in_array($file, ['index.php', 'settings.php', 'links.php', 'sync_floors.php', 'sync_master.php', 'sync_items.php', 'sync_logs.php'], true);

        if ($file === 'logout.php') {
            $logout[] = $item;
            continue;
        }

        if ($isFrequent) {
            $frequent[] = $item;
            continue;
        }

        if ($page['scope'] === 'legacy') {
            $legacy[] = $item;
            continue;
        }

        $others[] = $item;
    }

    usort($others, static fn(array $a, array $b): int => strcmp((string)$a['label'], (string)$b['label']));
    usort($legacy, static fn(array $a, array $b): int => strcmp((string)$a['label'], (string)$b['label']));

    $groups = [];
    if ($frequent !== []) {
        $groups[] = ['heading' => 'よく使う', 'items' => $frequent];
    }
    if ($others !== []) {
        $groups[] = ['heading' => 'その他の管理ページ', 'items' => $others];
    }
    if ($legacy !== []) {
        $groups[] = ['heading' => 'FANZA同期（/admin）', 'items' => $legacy];
    }
    if ($logout !== []) {
        $groups[] = ['heading' => 'アカウント', 'items' => $logout];
    }

    return $groups;
}
