if (!function_exists('render_shared_content_ad_row')) {
    function render_shared_content_ad_row(string $position_key, string $page_type): void
    {
        if (ad_current_device() !== 'pc') {
            return;
        }

        if ($position_key !== 'content_bottom') {
            return;
        }

        $prevUsedKeys = $GLOBALS['pcf_rss_widget_used_keys'] ?? null;
        $prevMaxItems = $GLOBALS['pcf_rss_widget_max_items'] ?? null;

        $GLOBALS['pcf_rss_widget_used_keys'] = [];
        $GLOBALS['pcf_rss_widget_max_items'] = 8;

        ob_start();
        include __DIR__ . '/rss_text_widget.php';
        $rssHtml = trim((string)ob_get_clean());

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

        $emptyWidget = '<div class="rss-widget rss-widget--text block"><div class="rss-box"><p class="sidebar-empty">テキストRSSの記事がありません。</p></div></div>';
        if ($rssHtml === '') {
            $rssHtml = $emptyWidget;
        }

        echo '<div class="content-ad-row only-pc">';
        echo '<div class="content-ad-row__rss">' . $rssHtml . '</div>';
        echo '</div>';
    }
}