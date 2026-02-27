<?php

declare(strict_types=1);

function admin_discover_pages(): array
{
    return [
        ['slug' => 'dashboard', 'title' => 'Dashboard', 'path' => 'admin/index.php'],
        ['slug' => 'settings', 'title' => 'Settings', 'path' => 'admin/settings.php'],
        ['slug' => 'sync_floors', 'title' => 'Sync Floors', 'path' => 'admin/sync_floors.php'],
        ['slug' => 'sync_master', 'title' => 'Sync Master', 'path' => 'admin/sync_master.php'],
        ['slug' => 'sync_items', 'title' => 'Sync Items', 'path' => 'admin/sync_items.php'],
        ['slug' => 'sync_logs', 'title' => 'Sync Logs', 'path' => 'admin/sync_logs.php'],
    ];
}
