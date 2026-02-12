<?php

declare(strict_types=1);

function admin_menu_groups(): array
{
    return [
        [
            'heading' => 'ダッシュボード',
            'items' => [
                ['file' => 'index.php', 'label' => 'ダッシュボード', 'status' => 'ready'],
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
                ['file' => 'import_items.php', 'label' => 'インポート', 'status' => 'ready'],
                ['file' => 'api_logs.php', 'label' => 'API履歴（準備中）', 'status' => 'coming_soon'],
            ],
        ],
        [
            'heading' => 'コンテンツ / 外部連携',
            'items' => [
                ['file' => 'rss.php', 'label' => 'RSSキャッシュ', 'status' => 'ready'],
                ['file' => 'links.php', 'label' => '相互リンク', 'status' => 'ready'],
                ['file' => 'ads.php', 'label' => 'コード挿入（広告枠）', 'status' => 'ready'],
                ['file' => 'pages.php', 'label' => '固定ページ', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => '解析',
            'items' => [
                ['file' => 'analytics.php', 'label' => 'PV / UU', 'status' => 'ready'],
            ],
        ],
        [
            'heading' => '設定',
            'items' => [
                ['file' => 'change_password.php', 'label' => 'パスワード変更', 'status' => 'ready'],
                ['file' => 'backup.php', 'label' => 'バックアップ', 'status' => 'ready'],
                ['file' => 'mail.php', 'label' => 'メール', 'status' => 'ready'],
                ['file' => 'users.php', 'label' => 'アカウント設定', 'status' => 'ready'],
                ['file' => 'design.php', 'label' => 'デザイン設定', 'status' => 'ready'],
                ['file' => 'seo.php', 'label' => 'SEO', 'status' => 'ready'],
            ],
        ],
    ];
}
