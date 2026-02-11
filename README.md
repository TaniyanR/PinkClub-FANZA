# PinkClub-FANZA

FANZA（DMM）APIから取得してDBへ保存した作品データを、**生PHP（Frameworkなし）のSSR**で表示するサイトです。

---

## 概要

* 管理画面で **FANZA API設定**を保存し、データを **DBへ取り込み（upsert）**
* フロントは **DBの実データ**を表示（一覧・詳細・タクソノミ別一覧）
* 外部ライブラリは原則なし（PHP + MySQL + PDO）

---

## できること（実装済み）

### 管理画面

* 管理画面ログイン（CSRF保護）
* 管理画面から API設定保存
* `import_items.php` による DMM API取得 → DB upsert
* DB初期化（テーブル作成）

  * `php scripts/init_db.php`
  * `/public/admin/db_init.php`

### フロント（すべてDB実データ表示）

* `/public/index.php`

  * 新着 / ピックアップ / 女優 / シリーズ / メーカー / ジャンル
* `/public/posts.php`

  * 作品一覧（検索・並び替え・件数・ページング）
* `/public/item.php?cid=...`

  * 作品詳細（タクソノミ、サンプル画像、関連作品）
* タクソノミ一覧と詳細

  * 女優：`/public/actresses.php` `/public/actress.php?id=...`
  * ジャンル：`/public/genres.php` `/public/genre.php?id=...`
  * メーカー：`/public/makers.php` `/public/maker.php?id=...`
  * シリーズ：`/public/series.php` `/public/series_one.php?id=...`
* 404ページ
* basic SEO（title / description / canonical / OGP）

---

## 未実装（旧構想から整理）

以下は現時点では **未実装** です。

* PV/UU解析
* アクセス解析 / 人気 / 逆アクセス
* 相互リンク管理
* RSS取得と表示
* 固定ページCMS
* sitemap.xml / robots.txt 自動生成
* GA4 / Search Console 連携
* デザイン設定
* コード挿入（広告枠）
* メール
* Backup
* アカウント設定（複数ユーザー等の拡張）
* フロント用の「ログイン（メール＋パス）」やログイン中表示（インジケータ）

---

## 管理ログイン（このリポジトリの簡易管理画面）

* ログインURL（固定）：`/login0718.php`

  * 旧URLが存在する場合は、このURLへリダイレクトする想定です。
* 初期ユーザー名：`admin`
* 初期パスワード：`admin12345`
* 初回ログイン後は `/public/admin/change_password.php` へ **強制遷移**し、パスワード変更が必須です。
* `config.local.php` に `admin` が未設定でも、上記初期資格情報でログインできます。

### `config.local.php` の `admin` 設定例

`password_hash()` で生成した値を設定します。

```php
'admin' => [
  'username' => 'admin',
  'password_hash' => '$2y$12$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
],
```

> `config.local.php` は機密情報を含むため **Gitにコミットしないでください**（`.gitignore` 済み）。

> セキュリティのため、初期資格情報（`admin` / `admin12345`）でログインした後は必ず新しいパスワードに変更してください。

---

## SEO / Security（方針・実装）

### SEO

* title / description
* canonical
* OGP（最低限）

### Security

* DBアクセスは **PDO + Prepared Statement**
* 出力は `e()` 等でエスケープ（XSS対策）
* 管理画面は既存のCSRF実装を維持
* セッション管理（ログイン時に必要な対策を実施）

※ Clickjacking対策・構造化データ・パンくず・sitemap/robots・HTTPS必須等は「方針として有効」ですが、README上で **実装済みと断言しない**範囲に留めます（実装したら追記してください）。

---

## Technology Stack

* PHP 8.x（Frameworkなし）
* MySQL 8.x
* PDO
* Vanilla JS（必要最小限）
* UTF-8
* Timezone: Asia/Tokyo

---

## URL設計（本番推奨）

本番では **`public/` をドキュメントルート**にするのを推奨します。

* 推奨（`public/` がWebルート）

  * トップ：`https://example.com/` → `public/index.php`
  * 管理ログイン：`https://example.com/login0718.php`

ドキュメントルート変更ができない場合は、`https://example.com/public/` のように `public/` 配下で運用してください。

---

## Setup（最小手順）

### 1) `config.local.php` を用意

1. `config.local.php.example` を `config.local.php` にコピー
2. DB接続情報（host/dbname/user/password）を設定
   ※ `config.local.php` は **コミットしない**（`.gitignore`）

### 2) DB作成 & テーブル作成

* MySQLでDBを作成（例：`pinkclub_fanza`）
* テーブル作成は以下いずれか

  * `/public/admin/db_init.php` を開いて実行
  * `php scripts/init_db.php` を実行
  * もしくは `schema.sql` を import（配置がある場合）

### 3) 管理画面でAPI設定 → 取り込み

1. `/login0718.php` でログイン
2. `/public/admin/settings.php` でFANZA API設定を保存
3. `/public/admin/import_items.php` でデータ取得（DBへ保存）

### 4) フロント確認

* `/public/index.php`（トップ）
* `/public/posts.php`（一覧）
* `/public/item.php?cid=...`（詳細）

---

## セキュリティ方針（要点）

* DBアクセスはPDOプリペアドステートメント
* 出力は `e()` でエスケープ
* 管理画面は既存のCSRF実装を維持
* `config.local.php` は機密のためコミット禁止