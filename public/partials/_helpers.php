<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/url.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/app_features.php';

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('safe_int')) {
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
}

if (!function_exists('safe_str')) {
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
}

if (!function_exists('build_url')) {
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
}

if (!function_exists('current_path')) {
    function current_path(): string
    {
        $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return $path !== '' ? $path : '/';
    }
}

if (!function_exists('canonical_url')) {
    function canonical_url(?string $path = null, array $query = []): string
    {
        $url = build_url($path ?? current_path(), $query);
        $base = rtrim(base_url(), '/');
        return $base !== '' ? ($base . $url) : $url;
    }
}

if (!function_exists('front_asset_url')) {
    function front_asset_url(string $path): string
    {
        $normalizedPath = '/' . ltrim($path, '/');
        $basePath = rtrim(base_path(), '/');

        if ($basePath !== '') {
            $asset = $basePath . $normalizedPath;
        } else {
            $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $asset = str_starts_with($requestPath, '/public/') ? ('/public' . $normalizedPath) : $normalizedPath;
        }

        return preg_replace('#^/public/public/#', '/public/', $asset) ?: $asset;
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? $value : date('Y/m/d', $ts);
    }
}

if (!function_exists('format_price')) {
    function format_price(mixed $price): string
    {
        if (!is_numeric($price)) {
            return '';
        }
        return '¥' . number_format((int)$price);
    }
}

if (!function_exists('parse_image_list')) {
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
}

if (!function_exists('paginate_items')) {
    function paginate_items(array $rows, int $limit): array
    {
        $hasNext = count($rows) > $limit;
        if ($hasNext) {
            array_pop($rows);
        }
        return [$rows, $hasNext];
    }
}

if (!function_exists('abort_404')) {
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
}

if (!function_exists('ad_default_display_rules')) {
    function ad_default_display_rules(): array
    {
        return [
            'pc' => [
                'header_left_728x90' => ['all' => true],
                'sidebar_bottom' => ['home' => true, 'list' => false, 'item' => false, 'page' => false],
                'content_top' => ['home' => false, 'list' => false, 'item' => true, 'page' => false],
                'content_bottom' => ['home' => false, 'list' => false, 'item' => true, 'page' => false],
            ],
            'sp' => [
                'sp_header_below' => ['home' => true, 'list' => true, 'item' => true, 'page' => true],
                'sp_footer_above' => ['home' => true, 'list' => true, 'item' => true, 'page' => true],
            ],
        ];
    }
}

if (!function_exists('ad_all_positions')) {
    function ad_all_positions(): array
    {
        return ['header_left_728x90', 'sidebar_bottom', 'content_top', 'content_bottom', 'sp_header_below', 'sp_footer_above'];
    }
}

if (!function_exists('ad_current_page_type')) {
    function ad_current_page_type(): string
    {
        $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === 'index.php' || $path === '/') {
            return 'home';
        }
        if ($script === 'posts.php' || $script === 'list.php') {
            return 'list';
        }
        if ($script === 'item.php') {
            return 'item';
        }
        if ($script === 'page.php' || str_starts_with($path, '/p/')) {
            return 'page';
        }
        return 'home';
    }
}

if (!function_exists('ad_current_device')) {
    function ad_current_device(): string
    {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua !== '' && preg_match('/iPhone|iPod|Android.+Mobile|Windows Phone/i', $ua) === 1) {
            return 'sp';
        }
        return 'pc';
    }
}

if (!function_exists('ad_display_rules')) {
    function ad_display_rules(): array
    {
        $raw = app_setting_get('ads_display_rules', null);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        $default = ad_default_display_rules();
        if (!is_array($raw)) {
            return $default;
        }
        return array_replace_recursive($default, $raw);
    }
}

if (!function_exists('ad_snippet_rows')) {
    function ad_snippet_rows(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        try {
            $stmt = db()->query('SELECT slot_key,snippet_html,is_enabled FROM code_snippets');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            if (function_exists('app_log_error')) {
                app_log_error('ad_snippet_rows failed', $e);
            }
            $cache = [];
            return $cache;
        }
        $cache = [];
        foreach ($rows as $row) {
            $slotKey = (string)($row['slot_key'] ?? '');
            if ($slotKey === '') {
                continue;
            }
            $cache[$slotKey] = [
                'snippet_html' => (string)($row['snippet_html'] ?? ''),
                'is_enabled' => (int)($row['is_enabled'] ?? 0) === 1,
            ];
        }
        return $cache;
    }
}

if (!function_exists('get_ad_code')) {
    function get_ad_code(string $position_key): ?string
    {
        $rows = ad_snippet_rows();
        $row = $rows[$position_key] ?? null;
        if (!is_array($row) || $row['is_enabled'] !== true) {
            return null;
        }
        $html = trim((string)$row['snippet_html']);
        return $html !== '' ? $html : null;
    }
}

if (!function_exists('should_show_ad')) {
    function should_show_ad(string $position_key, string $page_type, string $device): bool
    {
        if (get_ad_code($position_key) === null) {
            return false;
        }
        $rules = ad_display_rules();
        $deviceRules = $rules[$device] ?? null;
        if (!is_array($deviceRules)) {
            return false;
        }
        $positionRules = $deviceRules[$position_key] ?? null;
        if (!is_array($positionRules)) {
            return false;
        }
        if (array_key_exists('all', $positionRules)) {
            return (bool)$positionRules['all'];
        }
        return (bool)($positionRules[$page_type] ?? false);
    }
}

if (!function_exists('render_ad')) {
    function render_ad(string $position_key, string $page_type, string $device): void
    {
        if (!should_show_ad($position_key, $page_type, $device)) {
            return;
        }
        $html = get_ad_code($position_key);
        if ($html === null) {
            return;
        }
        echo $html;
    }
}

if (!function_exists('front_safe_text_setting')) {
    function front_safe_text_setting(string $key, string $default = ''): string
    {
        try {
            $value = site_setting_get($key, $default);
            return is_string($value) ? $value : $default;
        } catch (Throwable $e) {
            app_log_error('front_safe_text_setting failed: ' . $key, $e);
            return $default;
        }
    }
}
