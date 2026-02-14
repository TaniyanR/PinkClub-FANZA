<?php

declare(strict_types=1);

function admin_render(string $pageTitle, callable $renderer): void
{
    require_once __DIR__ . '/_common.php';

    ob_start();
    $renderer();
    $main = trim((string)ob_get_clean());

    if ($main === '') {
        $main = '<div class="admin-card"><p>このページは未実装、または無出力です。</p></div>';
    }

    $content = static function () use ($main): void {
        echo $main;
    };

    include __DIR__ . '/../partials/admin_layout.php';
}
