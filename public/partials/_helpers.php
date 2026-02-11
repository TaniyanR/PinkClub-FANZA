<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/url.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function safe_int(mixed $value, int $default = 0, int $min = 0, ?int $max = null): int
{
    if (is_int($value)) {
        $result = $value;
    } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
        $result = (int)trim($value);
    } else {
        $result = $default;
    }

    if ($result < $min) {
        $result = $min;
    }

    if ($max !== null && $result > $max) {
        $result = $max;
    }

    return $result;
}

function safe_str(mixed $value, int $maxLen = 200): string
{
    if (!is_string($value)) {
        return '';
    }

    $normalized = trim($value);
    if ($normalized === '') {
        return '';
    }

    if (mb_strlen($normalized) > $maxLen) {
        $normalized = mb_substr($normalized, 0, $maxLen);
    }

    return $normalized;
}

function build_url(string $path, array $query = []): string
{
    $filtered = [];
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $filtered[(string)$key] = (string)$value;
    }

    $qs = http_build_query($filtered);
    return $path . ($qs !== '' ? ('?' . $qs) : '');
}

function current_path(): string
{
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return $path !== '' ? $path : '/';
}

function canonical_url(?string $path = null, array $query = []): string
{
    $url = build_url($path ?? current_path(), $query);
    $base = rtrim(base_url(), '/');
    return $base !== '' ? ($base . $url) : $url;
}

function format_date(?string $value): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts === false ? $value : date('Y/m/d', $ts);
}

function format_price(mixed $price): string
{
    if (!is_numeric($price)) {
        return '';
    }
    return '¥' . number_format((int)$price);
}

function parse_image_list(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    $trimmed = trim($value);
    if ($trimmed !== '' && $trimmed[0] === '[') {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }
    }

    $parts = preg_split('/[\r\n,|\s]+/', $value);
    if (!is_array($parts)) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $parts), static fn(string $v): bool => $v !== ''));
}

function paginate_items(array $rows, int $limit): array
{
    $hasNext = count($rows) > $limit;
    if ($hasNext) {
        array_pop($rows);
    }
    return [$rows, $hasNext];
}

function abort_404(string $title = '404 Not Found', string $message = 'ページが見つかりません'): never
{
    http_response_code(404);

    $pageTitle = $title;
    $pageDescription = $message;
    $canonicalUrl = canonical_url('/404.php');
    $notFoundTitle = $title;
    $notFoundMessage = $message;

    include __DIR__ . '/header.php';
    include __DIR__ . '/nav_search.php';
    echo '<div class="layout">';
    include __DIR__ . '/sidebar.php';
    echo '<main class="main-content"><section class="block">';
    echo '<h1 class="section-title">' . e($notFoundTitle) . '</h1>';
    echo '<p>' . e($notFoundMessage) . '</p>';
    echo '<a class="button button--primary" href="/">トップへ戻る</a>';
    echo '</section></main></div>';
    include __DIR__ . '/footer.php';
    exit;
}
