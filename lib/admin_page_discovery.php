<?php

declare(strict_types=1);

/**
 * @return list<array{scope:string,path:string,file:string,label:string,priority:int,broken:bool,auth:string,exists:bool}>
 */
function admin_discover_pages(): array
{
    $pages = array_merge(
        admin_discover_pages_in_dir('public', __DIR__ . '/../public/admin', '/public/admin'),
        admin_discover_pages_in_dir('legacy', __DIR__ . '/../admin', '/admin')
    );

    usort($pages, static function (array $a, array $b): int {
        if ($a['priority'] !== $b['priority']) {
            return $a['priority'] <=> $b['priority'];
        }

        if ($a['scope'] !== $b['scope']) {
            return strcmp($a['scope'], $b['scope']);
        }

        return strcmp($a['file'], $b['file']);
    });

    return $pages;
}

/**
 * @return list<array{scope:string,path:string,file:string,label:string,priority:int,broken:bool,auth:string,exists:bool}>
 */
function admin_discover_pages_in_dir(string $scope, string $dirPath, string $routePrefix): array
{
    if (!is_dir($dirPath)) {
        return [];
    }

    $entries = scandir($dirPath);
    if (!is_array($entries)) {
        return [];
    }

    $pages = [];
    foreach ($entries as $entry) {
        if (!preg_match('/\.php$/', $entry)) {
            continue;
        }
        if ($entry[0] === '_' || str_starts_with($entry, 'save_')) {
            continue;
        }

        $fullPath = $dirPath . '/' . $entry;
        if (!is_file($fullPath)) {
            continue;
        }

        $label = admin_page_label($entry, $fullPath);
        $priority = admin_page_priority($entry);
        $actionLike = preg_match('/(?:_delete|_edit|_new|_show|verify_email|db_init)/', $entry) === 1;

        $pages[] = [
            'scope' => $scope,
            'path' => $routePrefix . '/' . $entry,
            'file' => $entry,
            'label' => $label,
            'priority' => $priority,
            'broken' => $actionLike,
            'auth' => '要ログイン',
            'exists' => true,
        ];
    }

    return $pages;
}

function admin_page_priority(string $file): int
{
    static $priorityMap = [
        'index.php' => 10,
        'settings.php' => 20,
        'links.php' => 30,
        'sync_floors.php' => 40,
        'sync_master.php' => 41,
        'sync_items.php' => 42,
        'sync_logs.php' => 43,
        'sitemap.php' => 90,
        'logout.php' => 999,
    ];

    return $priorityMap[$file] ?? 200;
}

function admin_page_label(string $file, string $fullPath): string
{
    static $labelMap = [
        'index.php' => 'ダッシュボード',
        'settings.php' => '設定',
        'links.php' => '相互リンク管理',
        'sync_floors.php' => 'フロア同期',
        'sync_master.php' => 'マスタ同期',
        'sync_items.php' => '商品同期',
        'sync_logs.php' => '同期ログ',
        'sitemap.php' => '管理ページ一覧',
        'logout.php' => 'ログアウト',
    ];

    if (isset($labelMap[$file])) {
        return $labelMap[$file];
    }

    $title = admin_extract_title_from_file($fullPath);
    if ($title !== '') {
        return $title;
    }

    $base = preg_replace('/\.php$/', '', $file);
    $base = is_string($base) ? $base : $file;
    $base = str_replace('_', ' ', $base);

    return trim($base) !== '' ? trim($base) : $file;
}

function admin_extract_title_from_file(string $fullPath): string
{
    $content = @file_get_contents($fullPath, false, null, 0, 5000);
    if (!is_string($content) || $content === '') {
        return '';
    }

    if (preg_match('/\$pageTitle\s*=\s*[\'\"]([^\'\"]+)[\'\"]/', $content, $m) === 1) {
        return trim((string)$m[1]);
    }
    if (preg_match('/\$title\s*=\s*[\'\"]([^\'\"]+)[\'\"]/', $content, $m) === 1) {
        return trim((string)$m[1]);
    }
    if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $content, $m) === 1) {
        return trim(strip_tags((string)$m[1]));
    }

    return '';
}
