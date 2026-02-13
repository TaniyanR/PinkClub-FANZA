<?php
declare(strict_types=1);

function admin_menu_groups(): array
{
    return [
        [
            'heading' => 'ダッシュボード',
            'items' => [
                ['file' => 'index.php', 'label' => '状態サマリー', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => '初期セットアップ',
            'items' => [
                ['file' => 'db_init.php', 'label' => 'DB初期化', 'status' => 'ready'],
                ['file' => 'settings.php', 'label' => 'API設定', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => 'データ取得',
            'items' => [
                ['file' => 'import_items.php', 'label' => 'インポート実行', 'status' => 'ready'],
                ['file' => 'api_logs.php', 'label' => 'API履歴', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => '運用',
            'items' => [
                ['file' => 'links.php', 'label' => '相互リンク管理', 'status' => 'ready'],
                ['file' => 'rss.php', 'label' => 'RSS管理', 'status' => 'ready'],
                ['file' => 'analytics.php', 'label' => 'アクセス解析', 'status' => 'ready'],
                ['file' => 'mail.php', 'label' => 'メールログ', 'status' => 'ready'],
                ['file' => 'backup.php', 'label' => 'バックアップ', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => 'サイト管理',
            'items' => [
                ['file' => 'pages.php', 'label' => '固定ページCMS', 'status' => 'ready'],
                ['file' => 'seo.php', 'label' => 'sitemap/robots/SEO', 'status' => 'ready'],
                ['file' => 'ads.php', 'label' => '広告コード', 'status' => 'ready'],
                ['file' => 'design.php', 'label' => 'デザイン設定', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => 'アカウント',
            'items' => [
                ['file' => 'users.php', 'label' => 'アカウント設定', 'status' => 'ready'],
                ['file' => 'change_password.php', 'label' => 'パスワード変更', 'status' => 'ready'],
            ],
        ],
    ];
}
