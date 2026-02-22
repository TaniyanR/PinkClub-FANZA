<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$pageTitle = '管理ヘルプ';
ob_start();
?>
<h1>管理画面ヘルプ</h1>

<div class="admin-card">
    <h2>1. 初期設定</h2>
    <ul>
        <li>まず「▶ 設定 → サイト設定」を開き、サイト名・サイトURL・管理者メールアドレスなどを保存します。</li>
        <li>次に「▶ アフィリエイト設定 → API設定」を開き、<strong>API ID</strong> と <strong>アフィリエイトID</strong> を設定します。</li>
        <li>API設定が未入力の状態では、インポートは実行できません（未設定メッセージが表示されます）。</li>
    </ul>
</div>

<div class="admin-card">
    <h2>2. インポートの使い方</h2>
    <ul>
        <li>管理メニューの「インポート」画面を開きます。</li>
        <li>必要に応じて件数・開始位置・ページ数・キーワードを指定し、「インポート」ボタンを押します。</li>
        <li>実行後は、画面上部の「直近のインポート結果」または「インポートエラー」で成功/失敗メッセージを確認します。</li>
        <li>「最終実行日時」に、最後に実行した日時が表示されます。</li>
    </ul>
</div>

<div class="admin-card">
    <h2>3. 取得済みデータの管理</h2>
    <ul>
        <li>インポート画面下部の一覧で、取得済みデータを新しい順に確認できます。</li>
        <li>一覧では、キーワード・状態・件数で絞り込みできます。</li>
        <li>「詳細」から item の詳細情報を確認できます。</li>
        <li>「削除」は1件ずつ実行します。<strong>削除は取り消しできません</strong>。実行前に対象を確認してください。</li>
    </ul>
</div>

<div class="admin-card">
    <h2>4. トラブル時の確認ポイント</h2>
    <ul>
        <li>「API設定が未設定です」と表示された場合は、API設定画面で API ID / アフィリエイトID が保存されているか確認してください。</li>
        <li>画面が開かない、またはエラー表示になる場合は、管理画面に再ログインして再実行してください。</li>
        <li>詳細なエラーはサーバーの <code>error_log</code> に記録されます。必要に応じてサーバー側ログを確認してください。</li>
        <li>URL が二重になる（例: ドメインが重複する）場合は、サイト設定の base URL（サイトURL）を確認してください。</li>
    </ul>
</div>
<?php
$content = (string)ob_get_clean();
include __DIR__ . '/../partials/admin_layout.php';
