# PinkClub FANZA

## セットアップ（XAMPP）
1. `C:\xampp\htdocs\pinkclub-fanza` に配置
2. XAMPPで Apache / MySQL を起動
3. `http://localhost/pinkclub-fanza/public/login0718.php` を開く

初回は `login0718.php` へのアクセスだけで、DB自動セットアップ（DB作成→schema適用→seed適用→admin/settings保証）が実行されます。  
失敗時のみ `http://localhost/pinkclub-fanza/public/setup_check.php` を開いて原因を確認してください。

## 固定URL / 認証
- 管理ログイン入口（固定）: `http://localhost/pinkclub-fanza/public/login0718.php`
- 管理トップ: `http://localhost/pinkclub-fanza/admin/index.php`
- 公開トップ: `http://localhost/pinkclub-fanza/public/`
- 初期管理者: `admin` / `password`

## 失敗時の確認
- エラー詳細は `logs/install.log` に記録されます。
- `setup_check.php` には失敗ステップ、例外、発生箇所、失敗SQL、ログ末尾を表示します。

## 補足
- 自動セットアップ実行は localhost / 127.0.0.1 / ::1 のみ。
- `login0718.php` / `setup_check.php` のCSSは `/assets/css/style.css` を共通利用します。

## マイグレーション適用
- インストーラーは `sql/schema.sql` 適用後に `sql/migrations/*.sql` をファイル名順で実行します。
- 実行済みは `migrations` テーブルで管理します。
- インストール完了後、`api_logs` と `api_schedules` が作成されていれば正常です。

## 管理画面の追加URL
- `http://localhost/pinkclub-fanza/admin/affiliate_api.php`（API設定 / テスト取得10件）
- `http://localhost/pinkclub-fanza/admin/api_timer.php`（タイマー実行API）
- `http://localhost/pinkclub-fanza/admin/auto_timer.php`（タイマー稼働ページ）
- `http://localhost/pinkclub-fanza/admin/site_settings.php`
- `http://localhost/pinkclub-fanza/admin/account_settings.php`
- `http://localhost/pinkclub-fanza/admin/design_settings.php`
- `http://localhost/pinkclub-fanza/admin/links_partner.php`
- `http://localhost/pinkclub-fanza/admin/links_rss.php`
- `http://localhost/pinkclub-fanza/admin/analytics.php`
- `http://localhost/pinkclub-fanza/admin/affiliate_ads.php`
- `http://localhost/pinkclub-fanza/admin/pages.php`
- `http://localhost/pinkclub-fanza/admin/pages_new.php`

## API設定→テスト取得→自動タイマー取得 手順
1. 管理画面 `http://localhost/pinkclub-fanza/admin/affiliate_api.php` を開きます。
2. `APIID` と `アフィリエイトID` を入力し、`商品取得件数`（100/200/300/500/1000）を選んで保存します。
3. `商品情報を10件取得（手動）` を押してテスト取得します（ItemList hits=10）。
4. 自動取得を使う場合は `タイマー自動取得` を `ON`、`実行間隔（分）` を設定して保存します。
5. `http://localhost/pinkclub-fanza/admin/auto_timer.php` を開いたままにすると、60秒ごとに `admin/timer_tick.php` を実行します（cron不要）。
6. tickごとに最大1種類のみ同期します（items → genres → makers → series → authors の順、各60分間隔）。
7. 手動10件テストは `item_sync_test_offset` を使い、本番offset (`item_sync_offset`) は進めません。

## リリース運用方針（速度優先・一括リリース）

### Done条件（完成定義）
- Must機能がすべて動作すること
- 重大/高優先の既知バグを0件にすること
- 一括リリース可能な最低限の動作確認が完了していること
- ログイン機能、CSS、その他既存機能にデグレがないこと

### 優先順位
- Must機能を最優先で固定し、Must以外は後回しにする

### 締切と品質バランス
- 速度優先で進める
- ただし致命的不具合は必ず修正する

### リリース方式
- リリースは一括で実施する

## 新規/更新された管理画面URL
- `http://localhost/pinkclub-fanza/admin/site_settings.php`（サイト名/URL/キャッチフレーズ/キーワード）
- `http://localhost/pinkclub-fanza/admin/links.php`（相互リンク管理）
- `http://localhost/pinkclub-fanza/admin/rss.php`（RSS管理）
- `http://localhost/pinkclub-fanza/admin/analytics.php`（アクセス解析）
- `http://localhost/pinkclub-fanza/admin/affiliate_api.php`（API設定/手動10件取得/タイマー状態）


## セットアップ確認で `settings(installer.ready=1)` が NG の場合
- `public/setup_check.php` を再読込してください（既存DBの設定テーブルが初回アクセス時に正規化される場合があります）。
- それでもNGの場合は `public/login0718.php` にアクセスしてセットアップを再実行してください。

## PR運用メモ（競合時）
- マージ競合が解消しづらいPRはクローズし、最新の `main` を取り込んだ新規PRとして再作成してください。
- 再作成PRには「どのPRを置き換えたか」を明記するとレビューがスムーズです。
