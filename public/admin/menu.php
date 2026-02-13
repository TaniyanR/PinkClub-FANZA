<?php
declare(strict_types=1);

function admin_menu_groups(): array
{
    return [
        [
            'heading' => '設定',
            'items' => [
                ['file' => 'settings_site.php', 'label' => 'サイト設定'],
                ['file' => 'users.php', 'label' => 'アカウント設定'],
                ['file' => 'design.php', 'label' => 'デザイン設定'],
                ['file' => 'settings_import_export.php', 'label' => 'インポート、エクスポート'],
            ],
        ],
        [
            'heading' => 'リンク設定',
            'items' => [
                ['file' => 'links.php', 'label' => '相互リンク管理'],
                ['file' => 'rss.php', 'label' => 'RSS管理'],
            ],
        ],
        [
            'standalone' => true,
            'items' => [
                ['file' => 'analytics.php', 'label' => 'アクセス解析'],
            ],
        ],
        [
            'heading' => 'アフィリエイト設定',
            'items' => [
                ['file' => 'api_settings.php', 'label' => 'API設定'],
                ['file' => 'ads.php', 'label' => '広告コード'],
            ],
        ],
        [
            'heading' => '固定ページ',
            'items' => [
                ['file' => 'pages_new.php', 'label' => '新規'],
                ['file' => 'pages.php', 'label' => '編集'],
            ],
        ],
        [
            'standalone' => true,
            'items' => [
                ['file' => 'mail.php', 'label' => 'メール'],
            ],
        ],
    ];
}
