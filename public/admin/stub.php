<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

function admin_stub_definitions(): array
{
    return [
        'links.php' => [
            'title' => '相互リンク管理',
            'todos' => ['申請一覧', '承認/却下フロー', '表示設定（PC/SP）', 'RSS連携ON/OFF'],
        ],
        'mail.php' => [
            'title' => 'メール',
            'todos' => ['送信テンプレート管理', '配信履歴確認', '送信キュー/再送', '監査ログ'],
        ],
        'rss.php' => [
            'title' => 'RSS管理',
            'todos' => ['RSSソース管理', '手動/定期フェッチ', '重複除外と更新制御', '取得エラーログ'],
        ],
        'analytics.php' => [
            'title' => 'アクセス解析',
            'todos' => ['PV/UU集計', '人気ページ分析', '流入元分析', '期間比較レポート'],
        ],
        'backup.php' => [
            'title' => 'バックアップ',
            'todos' => ['DBバックアップ', '設定ファイルの保存', '世代管理', '復元フロー'],
        ],
        'ads.php' => [
            'title' => 'コード挿入 / 広告枠',
            'todos' => ['広告枠管理', 'タグ挿入管理', '表示条件設定'],
        ],
        'pages.php' => [
            'title' => '固定ページCMS',
            'todos' => ['ページ一覧', 'ページ作成/編集', '公開設定', 'メタ情報管理'],
        ],
        'seo.php' => [
            'title' => 'sitemap / robots / SEO',
            'todos' => ['sitemap管理', 'robots.txt管理', 'SEO基本設定'],
        ],
        'users.php' => [
            'title' => 'アカウント設定',
            'todos' => ['管理者一覧', '追加/無効化', '権限管理', '監査ログ'],
        ],
        'api_logs.php' => [
            'title' => 'API履歴',
            'todos' => ['リクエスト履歴一覧', '成功/失敗の集計', '失敗時の再試行導線'],
        ],
        'design.php' => [
            'title' => 'デザイン設定',
            'todos' => ['サイト表示設定', 'テーマ色管理', 'ロゴ/OGP設定'],
        ],
    ];
}

function admin_render_stub(string $page): void
{
    $definitions = admin_stub_definitions();
    $entry = $definitions[$page] ?? null;

    $title = is_array($entry) ? (string)($entry['title'] ?? '準備中') : '準備中';
    $todos = is_array($entry) ? (array)($entry['todos'] ?? []) : [];

    $pageTitle = $title;
    ob_start();
    ?>
    <h1><?php echo e($title); ?></h1>
    <div class="admin-card">
        <p><strong>準備中</strong></p>
        <p>この機能は現在実装中です。管理画面の白画面防止のため、先に導線を公開しています。</p>

        <?php if ($todos !== []) : ?>
            <h2>実装予定</h2>
            <ul>
                <?php foreach ($todos as $todo) : ?>
                    <li><?php echo e((string)$todo); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    $content = (string)ob_get_clean();
    include __DIR__ . '/../partials/admin_layout.php';
}

$page = basename((string)($_GET['page'] ?? ''));
if ($page === '') {
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
}

admin_render_stub($page);
