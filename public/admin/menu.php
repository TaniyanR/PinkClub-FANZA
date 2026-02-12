<?php

declare(strict_types=1);

return [
    '基本' => [
        ['file' => 'index.php', 'label' => 'ダッシュボード'],
        ['file' => 'settings.php', 'label' => '管理設定'],
        ['file' => 'import_items.php', 'label' => 'インポート'],
    ],
    '運用' => [
        ['file' => 'links.php', 'label' => '相互リンク管理'],
        ['file' => 'mail.php', 'label' => 'メール'],
        ['file' => 'rss.php', 'label' => 'RSS'],
        ['file' => 'analytics.php', 'label' => 'アクセス解析'],
        ['file' => 'backup.php', 'label' => 'バックアップ'],
    ],
    'サイト管理' => [
        ['file' => 'pages.php', 'label' => '固定ページCMS'],
        ['file' => 'seo.php', 'label' => 'sitemap/robots/SEO'],
        ['file' => 'ads.php', 'label' => 'コード挿入/広告枠'],
        ['file' => 'design.php', 'label' => 'デザイン設定'],
    ],
    'アカウント' => [
        ['file' => 'users.php', 'label' => 'アカウント設定'],
        ['file' => 'change_password.php', 'label' => 'パスワード変更'],
    ],
];
