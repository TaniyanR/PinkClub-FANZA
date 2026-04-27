<?php

if (!function_exists('render_shared_content_ad_row')) {
    function render_shared_content_ad_row(string $position_key, string $page_type): void
    {
        if (function_exists('ad_current_device') && ad_current_device() !== 'pc') {
            return;
        }

        // Keep this helper limited to bottom placement to avoid top-of-content duplication.
        if ($position_key !== 'content_bottom') {
            return;
        }

        $prevUsedKeys = $GLOBALS['pcf_rss_widget_used_keys'] ?? null;
        $prevMaxItems = $GLOBALS['pcf_rss_widget_max_items'] ?? null;

        // Reset widget tracking so left/right includes render independent feeds in this row.
        $GLOBALS['pcf_rss_widget_used_keys'] = [];
        $GLOBALS['pcf_rss_widget_max_items'] = 8;

        ob_start();
        include __DIR__ . '/rss_text_widget.php';
        $leftRssHtml = trim((string)ob_get_clean());

        ob_start();
        include __DIR__ . '/rss_text_widget.php';
        $rightRssHtml = trim((string)ob_get_clean());

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
        if ($leftRssHtml === '') {
            $leftRssHtml = $emptyWidget;
        }
        if ($rightRssHtml === '') {
            $rightRssHtml = $emptyWidget;
        }

        echo '<div class="content-ad-row content-ad-row--rss-split" style="margin-top:20px;">';
        echo '<div class="content-ad-row__rss">' . $leftRssHtml . '</div>';
        echo '<div class="content-ad-row__rss">' . $rightRssHtml . '</div>';
        echo '</div>';
    }
}
