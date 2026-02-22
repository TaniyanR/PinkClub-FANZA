<?php

declare(strict_types=1);

require_once __DIR__ . '/_page.php';

admin_render('運用ヘルプ', static function (): void {
    ?>
    <h1>管理画面ヘルプ</h1>

    <section class="admin-card">
        <h2>このツールでできること</h2>
        <ul>
            <li>設定管理（サイト設定・API設定・デザイン設定）</li>
            <li>固定ページの作成 / 編集 / 削除</li>
            <li>FANZA API からの商品インポート（手動）</li>
            <li>取得済みデータの一覧確認（簡易フィルタ）</li>
            <li>商品詳細確認 / 1件削除</li>
        </ul>
        <p>主な流れは <strong>設定 → インポート → 一覧/詳細確認 → 削除</strong> です。</p>
    </section>

    <section class="admin-card">
        <h2>初期設定</h2>
        <ol>
            <li>サイト設定を保存する</li>
            <li>API設定で API ID / Affiliate ID / フロアを保存する（site は FANZA 固定）</li>
            <li>必要に応じてデザイン設定を保存する</li>
        </ol>
        <p>必要テーブルは管理画面アクセス時に自動作成されます（手動のテーブル初期化は不要です）。</p>
        <p><strong>API ID / Affiliate ID / フロア が未設定の場合、インポートは実行できません。</strong></p>
    </section>

    <section class="admin-card">
        <h2>インポート運用</h2>
        <ul>
            <li><code>import_items.php</code> から手動実行します。</li>
            <li>実行結果はフラッシュメッセージで確認できます。</li>
            <li>最終実行日時を画面で確認できます。</li>
            <li>一覧はキーワード / 状態 / 件数で簡易フィルタできます。</li>
            <li><code>item_show.php</code> で詳細確認、<code>item_delete.php</code> で1件削除できます。</li>
        </ul>
        <p><strong>削除は取り消しできません。</strong></p>
    </section>

    <section class="admin-card">
        <h2>トラブル時の確認</h2>
        <ul>
            <li>API 未設定メッセージが出た場合は API 設定の保存状態を確認する</li>
            <li>画面エラー時は <code>storage/logs/php-error.log</code> とサーバーの <code>error_log</code> を確認する</li>
            <li>URL 二重連結（例: <code>/public/public/</code>）が出る場合は base URL / SITE_URL を確認する</li>
        </ul>
    </section>

    <section class="admin-card">
        <h2>運用前チェックリスト</h2>
        <ul>
            <li>ログインできる</li>
            <li>設定3画面（サイト / API / デザイン）が保存できる</li>
            <li>インポート画面が開く</li>
            <li>API未設定時メッセージが出る</li>
            <li>インポート実行できる</li>
            <li>最終実行日時が表示される</li>
            <li>一覧フィルタ（キーワード / 状態 / 件数）が動く</li>
            <li>詳細画面が開く</li>
            <li>削除できる（取り消し不可）</li>
            <li>URL二重連結が出ていない</li>
        </ul>
    </section>

    <section class="admin-card">
        <h2>未実装（将来拡張）</h2>
        <ul>
            <li>cron による自動実行</li>
            <li>タグ自動生成</li>
            <li>関連記事連携</li>
            <li>高度検索 / 並び替え / ページネーション強化</li>
        </ul>
    </section>
    <?php
});