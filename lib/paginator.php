<?php
declare(strict_types=1);

function paginate(int $total, int $page = 1, int $perPage = 20): array
{
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    ];
}
