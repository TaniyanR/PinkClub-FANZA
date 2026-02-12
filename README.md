# PinkClub-FANZA

生PHP SSR / MySQL / PDO で構成した FANZAデータ表示サイト + WordPress風管理画面です。

## 実装機能（最新版）

- 管理ログイン（`admin/password`。`config.local.php` の `admin.password_hash` 優先）
- 管理画面UI刷新（サイドバー + トップバー + ダッシュボード）
- API設定 / インポート / DB初期化
- PV/UU解析（人気作品 / 参照元ドメイン）
- 固定ページCMS（一覧 / 作成 / 編集 / 削除）
- sitemap.xml / robots.txt 自動生成
- SEO設定（GA4 / Search Console / canonical base）
- デザイン設定（サイト名 / 色 / OGP / フッタ）
- 広告/コード挿入（head / body end / sidebar / 記事内）
- お問い合わせフォーム + 送信ログ
- Backup（DB簡易ダンプSQL / 設定JSON）
- 相互リンク管理（pending/approved/rejected/disabled）
- RSS取得（手動更新 + フロント軽量自動更新）
- 管理者アカウント拡張（`admin_users`）
- フロント会員ログイン（メール+パス） + ヘッダインジケータ

## 主要URL

### フロント
- `/public/index.php`
- `/public/posts.php`
- `/public/item.php?cid=...`
- `/public/page.php?slug=about`
- `/public/contact.php`
- `/public/links.php`
- `/public/rss.php`
- `/public/user_login.php`
- `/public/user_logout.php`
- `/public/user_register.php`
- `/public/sitemap.xml`
- `/public/robots.txt`

### 管理
- `/public/login0718.php`
- `/public/admin/index.php`
- `/public/admin/settings.php`
- `/public/admin/import_items.php`
- `/public/admin/analytics.php`
- `/public/admin/pages.php`
- `/public/admin/page_edit.php`
- `/public/admin/seo.php`
- `/public/admin/design.php`
- `/public/admin/ads.php`
- `/public/admin/mail.php`
- `/public/admin/backup.php`
- `/public/admin/links.php`
- `/public/admin/rss.php`
- `/public/admin/users.php`
- `/public/admin/change_password.php`

## セキュリティ方針
- PDO + プリペアドステートメント
- CSRFトークン（管理POST + 主要フォーム）
- XSS対策（`e()` / `htmlspecialchars`）
- `config.local.php` は機密扱い（Git管理しない）

## スキーマ
- `sql/schema.sql`（`db/schema.sql` に同期）
