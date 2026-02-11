<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function base_url(): string
{
    return rtrim((string)config_get('site.base_url', ''), '/');
}

function current_path(): string
{
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return $path !== '' ? $path : '/';
}

function canonical_url(?string $path = null, array $query = []): string
{
    $targetPath = $path ?? current_path();
    $qs = http_build_query($query);
    $suffix = $qs !== '' ? ('?' . $qs) : '';
    $url = $targetPath . $suffix;

    if (base_url() !== '') {
        return base_url() . $url;
    }

    return $url;
}

function format_date(?string $value): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    return date('Y/m/d', $ts);
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
    if ($trimmed[0] === '[') {
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
