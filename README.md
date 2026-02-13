# PinkClub-FANZA

生PHP（SSR）/ MySQL8（PDO）構成の FANZA 系サイトです。UTF-8 / Asia/Tokyo 固定で動作します。

## 実装済み機能

- フロント: トップ/一覧/詳細/タクソノミ/固定ページ/リンク/RSS/問い合わせ
- 管理ログイン: `public/login0718.php`（初期 `admin / password`）
- 管理画面（WordPress風）
  - ダッシュボード
  - DB初期化 / API設定 / インポート / API履歴・内部タイマー
  - 相互リンク管理（申請承認・表示位置・RSS ON/OFF）
  - RSS管理（ソースCRUD・手動取得・キャッシュ表示）
  - アクセス解析（PV/UU/Referrer/IN/OUT）
  - メールログ/通知先
  - バックアップ（SQLダウンロード）
  - 固定ページCMS
  - sitemap/robots/SEO設定
  - 広告コード挿入
  - ユーザー管理（admin_users CRUD）
- パスワード再発行: `forgot_password.php` → token発行 → `reset_password.php`
- 例外・fatal error のログ化（`logs/app.log`）と dev/prod 切替

## セキュリティ

- CSRFトークン検証（管理/主要フォーム）
- PDOプリペアドステートメント
- XSS対策（`e()`）
- Clickjacking対策（`X-Frame-Options: SAMEORIGIN`）

## セットアップ

1. `config.local.php` を作成（DB接続）
2. `public/login0718.php` でログイン（`admin / password`）
3. `public/admin/db_init.php` で初期化
4. `public/admin/settings.php` でAPI設定
5. `public/admin/import_items.php` 実行

## 自己テスト手順

1. DB設定
2. DB初期化
3. API設定
4. インポート
5. フロント表示確認
6. 相互リンク申請→承認→表示→IN/OUT計測
7. RSSソース追加→取得→表示
8. 解析画面確認
9. バックアップDL確認
