# PinkClub-FANZA

## セットアップ（サーバー）
1. リポジトリ一式をサーバーへ配置します。
2. ブラウザで `/public/setup_check.php` を開きます。
3. DBホスト、DBポート、DB名、DBユーザー、DBパスワードを入力して「DB設定を保存してセットアップ実行」を押します。
4. セットアップ完了後、管理ログイン画面へ移動します。

## 初期ログイン
- 管理ログイン入口（固定）: `/public/login0718.php`
- 管理トップ: `/admin/index.php`
- 公開トップ: `/public/`
- 初期管理者: `admin`
- 初期パスワードはセットアップ時に `logs/install.log` へ記録されます。

## 設定ファイル
- DB設定は設置時に入力され、`config.local.php` に保存されます。
- `config.local.php` はGit管理しないでください。
- サーバー移転やDB変更時は `/public/setup_check.php` からDB設定を再入力してください。

## 主な管理画面
- `/admin/api_items.php`（商品情報API設定）
- `/admin/api_genres.php`（ジャンルAPI設定）
- `/admin/api_actresses.php`（女優API設定）
- `/admin/api_series.php`（シリーズAPI設定）
- `/admin/api_timer.php`（タイマー実行API）
- `/admin/auto_timer.php`（タイマー稼働ページ）
- `/admin/site_settings.php`
- `/admin/account_settings.php`
- `/admin/design_settings.php`
- `/admin/link_partners.php`
- `/admin/links_rss.php`
- `/admin/analytics.php`
- `/admin/affiliate_ads.php`
- `/admin/pages.php`
- `/admin/pages_new.php`

## タイマー同期
`/admin/auto_timer.php` を開いたままにすると、60秒ごとに `admin/timer_tick.php` を実行します（cron不要）。
