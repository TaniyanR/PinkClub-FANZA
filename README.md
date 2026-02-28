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
- `http://localhost/pinkclub-fanza/admin/site_settings.php`
- `http://localhost/pinkclub-fanza/admin/account_settings.php`
- `http://localhost/pinkclub-fanza/admin/design_settings.php`
- `http://localhost/pinkclub-fanza/admin/links_partner.php`
- `http://localhost/pinkclub-fanza/admin/links_rss.php`
- `http://localhost/pinkclub-fanza/admin/analytics.php`
- `http://localhost/pinkclub-fanza/admin/affiliate_ads.php`
- `http://localhost/pinkclub-fanza/admin/pages.php`
- `http://localhost/pinkclub-fanza/admin/pages_new.php`
