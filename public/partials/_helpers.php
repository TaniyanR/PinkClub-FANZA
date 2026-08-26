<?php

if (!function_exists('get_ad_code')) {
    function get_ad_code(string $position_key): ?string
    {
        if (!function_exists('db')) {
            return null;
        }

        try {
            $stmt = db()->prepare('SELECT snippet_html FROM code_snippets WHERE slot_key = :slot AND is_enabled = 1 LIMIT 1');
            $stmt->execute([':slot' => $position_key]);
            $html = $stmt->fetchColumn();
            $code = is_string($html) ? trim($html) : '';
            return $code !== '' ? $code : null;
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('render_ad')) {
    function render_ad(string $position_key, string $page_type = 'home', string $device = 'pc'): void
    {
        $html = get_ad_code($position_key);
        if ($html === null) {
            return;
        }
        echo $html;
    }
}

if (!function_exists('should_show_ad')) {
    function should_show_ad(string $position_key, string $page_type = 'home', string $device = 'pc'): bool
    {
        return get_ad_code($position_key) !== null;
    }
}

if (!function_exists('render_shared_text_rss_widget')) {
    function render_shared_text_rss_widget(): void
    {
        $prevUsedKeys = $GLOBALS['pcf_rss_widget_used_keys'] ?? null;
        $prevMaxItems = $GLOBALS['pcf_rss_widget_max_items'] ?? null;

        $GLOBALS['pcf_rss_widget_used_keys'] = [];
        unset($GLOBALS['pcf_rss_widget_max_items']);

        include __DIR__ . '/rss_text_widget.php';

        if ($prevUsedKeys === null) {
            unset($GLOBALS['pcf_rss_widget_used_keys']);
        } else {
            $GLOBALS['pcf_rss_widget_used_keys'] = $prevUsedKeys;
        }

        if ($prevMaxItems === null) {
            unset($GLOBALS['pcf_rss_widget_max_items']);
        } else {
            $GLOBALS['pcf_rss_widget_max_items'] = $prevMaxItems;
        }
    }
}

if (!function_exists('render_shared_mobile_rss_widget')) {
    function render_shared_mobile_rss_widget(): void
    {
        $prevUsedKeys = $GLOBALS['pcf_rss_widget_used_keys'] ?? null;
        $prevMaxItems = $GLOBALS['pcf_rss_widget_max_items'] ?? null;

        $GLOBALS['pcf_rss_widget_used_keys'] = [];
        unset($GLOBALS['pcf_rss_widget_max_items']);

        include __DIR__ . '/rss_text_widget.php';

        if ($prevUsedKeys === null) {
            unset($GLOBALS['pcf_rss_widget_used_keys']);
        } else {
            $GLOBALS['pcf_rss_widget_used_keys'] = $prevUsedKeys;
        }

        if ($prevMaxItems === null) {
            unset($GLOBALS['pcf_rss_widget_max_items']);
        } else {
            $GLOBALS['pcf_rss_widget_max_items'] = $prevMaxItems;
        }
    }
}

if (!function_exists('render_shared_content_ad_row')) {
    function render_shared_content_ad_row(string $position_key, string $page_type): void
    {
        if ($position_key !== 'content_bottom') {
            return;
        }

        require_once __DIR__ . '/../../lib/app_features.php';
        require_once __DIR__ . '/../../lib/rss_display_balance.php';

        $items = [];
        try {
            rss_widget_bootstrap(false);
            $candidates = rss_pick_display_items(1000, false, 14);
            if (count($candidates) > 1) {
                $candidates = rss_balance_items_by_partner_site($candidates);
            }

            $seenKeys = [];
            $seenTitles = [];
            $siteCounts = [];
            $perSiteLimit = 10;
            $maxTotal = 100;

            foreach ($candidates as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $key = rss_normalize_display_key($item);
                if ($key === '') {
                    $key = mb_strtolower(trim((string)($item['title'] ?? '')));
                }
                if ($key !== '' && isset($seenKeys[$key])) {
                    continue;
                }

                $titleKey = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string)($item['title'] ?? ''))) ?? '');
                if ($titleKey !== '' && isset($seenTitles[$titleKey])) {
                    continue;
                }

                $siteKey = rss_partner_display_source_key($item);
                if ($siteKey !== '' && ($siteCounts[$siteKey] ?? 0) >= $perSiteLimit) {
                    continue;
                }

                if ($key !== '') {
                    $seenKeys[$key] = true;
                }
                if ($titleKey !== '') {
                    $seenTitles[$titleKey] = true;
                }
                if ($siteKey !== '') {
                    $siteCounts[$siteKey] = ($siteCounts[$siteKey] ?? 0) + 1;
                }

                $items[] = $item;
                if (count($items) >= $maxTotal) {
                    break;
                }
            }

            $items = rss_spread_items_by_partner_site($items);
        } catch (Throwable $e) {
            error_log('[rss] bottom split widget skipped: ' . $e->getMessage());
            $items = [];
        }

        $half = (int)ceil(count($items) / 2);
        $leftItems = array_slice($items, 0, $half);
        $rightItems = array_slice($items, $half);

        $renderColumn = static function (array $columnItems): string {
            ob_start();
            echo '<div class="rss-widget rss-widget--text block"><div class="rss-box">';
            if ($columnItems === []) {
                echo '<p class="sidebar-empty">テキストRSSの記事がありません。</p>';
            } else {
                echo '<ul class="rss-list">';
                foreach ($columnItems as $item) {
                    echo '<li class="rss-list__item"><a href="' . e((string)($item['link'] ?? '')) . '" target="_blank" rel="noopener noreferrer">' . e((string)($item['title'] ?? '')) . '</a></li>';
                }
                echo '</ul>';
            }
            echo '</div></div>';
            return (string)ob_get_clean();
        };

        echo '<div class="content-ad-row content-ad-row--rss-split" style="margin-top:20px;">';
        echo '<div class="content-ad-row__rss">' . $renderColumn($leftItems) . '</div>';
        echo '<div class="content-ad-row__rss">' . $renderColumn($rightItems) . '</div>';
        echo '</div>';
    }
}
