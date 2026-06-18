# PinkClub F

## セットアップ
1. サーバーの公開ディレクトリに配置
2. `public/setup_check.php` を開き、サーバーパネルに表示されるDB接続情報を保存
3. `public/login0718.php` を開く

初回はDB接続設定を保存したうえで、セットアップ（DB作成→schema適用→seed適用→admin/settings保証）を実行します。
失敗時のみ `public/setup_check.php` を開いて原因を確認してください。

## 固定URL / 認証
- 管理ログイン入口（固定）: `/public/login0718.php`
- 管理トップ: `/admin/index.php`
- 公開トップ: `/public/`
- 初期管理者: `admin` / `password`

## 失敗時の確認
- エラー詳細は `logs/install.log` に記録されます。
- `setup_check.php` には失敗ステップ、例外、発生箇所、失敗SQL、ログ末尾を表示します。

## 補足
- DBホスト名は通常 `localhost` です。DB名・ユーザー名・パスワードはサーバーパネルのMySQL設定で確認し、対象データベースにMySQLユーザーを追加してから `public/setup_check.php` で保存してください。
- `login0718.php` / `setup_check.php` のCSSは `/assets/css/style.css` を共通利用します。

## マイグレーション適用
- インストーラーは `sql/schema.sql` 適用後に `sql/migrations/*.sql` をファイル名順で実行します。
- 実行済みは `migrations` テーブルで管理します。
- インストール完了後、`api_logs` と `api_schedules` が作成されていれば正常です。

## 管理画面の追加URL
- `/admin/api_items.php`（商品情報API設定）
- `/admin/api_genres.php`（ジャンルAPI設定）
- `/admin/api_actresses.php`（女優API設定）
- `/admin/api_series.php`（シリーズAPI設定）
- `/admin/api_timer.php`（タイマー実行API）
- `/admin/auto_timer.php`（タイマー稼働ページ）
- `/admin/site_settings.php`
- `/admin/account_settings.php`
- `/admin/design_settings.php`
- `/admin/links_partner.php`
- `/admin/links_rss.php`
- `/admin/analytics.php`
- `/admin/affiliate_ads.php`
- `/admin/pages.php`
- `/admin/pages_new.php`

## API設定→テスト取得→自動タイマー取得 手順
1. 管理画面で以下4ページを開き、それぞれ `APIID` / `アフィリエイトID` を保存します。
   - `admin/api_items.php`（商品情報）
   - `admin/api_genres.php`（ジャンル）
   - `admin/api_actresses.php`（女優）
   - `admin/api_series.php`（シリーズ）
2. 各ページの「10件テスト取得」で接続確認します。
3. 自動取得を使う場合は `item_sync_enabled` をONにします。
4. `/admin/auto_timer.php` を開いたままにすると、60秒ごとに `admin/timer_tick.php` を実行します（cron不要）。
5. tickごとに最大1種類のみ同期します（items → genres → actresses → series の順、各60分間隔）。

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
- `/admin/site_settings.php`（サイト名/URL/キャッチフレーズ/キーワード）
- `/admin/links.php`（相互リンク管理）
- `/admin/rss.php`（RSS管理）
- `/admin/analytics.php`（アクセス解析）
- `/admin/affiliate_api.php`（API設定/手動10件取得/タイマー状態）


## セットアップ確認で `settings(installer.ready=1)` が NG の場合
- `public/setup_check.php` を再読込してください（既存DBの設定テーブルが初回アクセス時に正規化される場合があります）。
- それでもNGの場合は `public/login0718.php` にアクセスしてセットアップを再実行してください。

## PR運用メモ（競合時）
- マージ競合が解消しづらいPRはクローズし、最新の `main` を取り込んだ新規PRとして再作成してください。
- 再作成PRには「どのPRを置き換えたか」を明記するとレビューがスムーズです。
