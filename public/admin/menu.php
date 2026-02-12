<?php
declare(strict_types=1);

function admin_menu_groups(): array
{
    return [
        [
            'heading' => '基本',
            'items' => [
                ['file' => 'index.php', 'label' => 'ダッシュボード', 'status' => 'ready'],
                ['file' => 'settings.php', 'label' => '管理設定', 'status' => 'ready'],
                ['file' => 'db_init.php', 'label' => 'DB初期化', 'status' => 'ready'],
                ['file' => 'import_items.php', 'label' => 'インポート', 'status' => 'ready'],
                ['file' => 'api_logs.php', 'label' => 'API履歴', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => 'コンテンツ',
            'items' => [
                ['file' => 'tags.php', 'label' => 'タグ管理', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => '運用',
            'items' => [
                ['file' => 'links.php', 'label' => '相互リンク管理', 'status' => 'coming_soon'],
                ['file' => 'rss.php', 'label' => 'RSS', 'status' => 'coming_soon'],
                ['file' => 'analytics.php', 'label' => 'アクセス解析', 'status' => 'coming_soon'],
                ['file' => 'mail.php', 'label' => 'メール', 'status' => 'coming_soon'],
                ['file' => 'backup.php', 'label' => 'バックアップ', 'status' => 'coming_soon'],
            ],
        ],
        [
            'heading' => 'サイト管理',
            'items' => [
                ['file' => 'pages.php', 'label' => '固定ページCMS', 'status' => 'coming_soon'],
                ['file' => 'seo.php', 'label' => 'sitemap/robots/SEO', 'status' => 'coming_soon'],
                ['file' => 'ads.php', 'label' => 'コード挿入/広告枠', 'status' => 'coming_soon'],
                ['file' => 'design.php', 'label' => 'デザイン設定', 'status' => 'coming_soon'],
            ],
        ],
        [
            'heading' => 'アカウント',
            'items' => [
                ['file' => 'users.php', 'label' => 'アカウント設定', 'status' => 'coming_soon'],
                ['file' => 'change_password.php', 'label' => 'パスワード変更', 'status' => 'ready'],
            ],
        ],
    ];
}
