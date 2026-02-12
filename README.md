# PinkClub-FANZA

生PHP（SSR）/ MySQL（PDO）で構成した FANZA系データ表示サイト + 管理画面です。  
本リポジトリは **フロントの真っ白防止** と **管理UIの整理** を含む最新版です。

---

## 今ある機能

- フロント表示（トップ / 投稿一覧 / 作品詳細 / 女優 / シリーズ / メーカー / ジャンル）
- 管理ログイン（入口: `public/login0718.php`）
- DB初期化、API設定、データインポート
- 固定ページ、RSS、相互リンク、解析、デザイン設定、SEO、広告コード管理
- 管理パスワード変更（任意）
- パスワード再設定（`admin_reset_token` を使うトークン方式）
- フロントの共通ブートストラップ（DB未設定時に案内画面表示、真っ白防止）

## これからの機能（準備中）

- API履歴画面（メニュー枠のみ）
- 管理機能の追加拡張（バックアップ詳細、メール高度化など）

---

## ローカル起動（XAMPP想定）

### 1) 配置

- 例: `C:\xampp\htdocs\pinkclub-fanza\`

### 2) 設定ファイル作成

- `config.local.php.example` を参考に `config.local.php` を作成
- 最低限、`db` 設定を記載
- 任意で `admin_reset_token` も設定（パスワード再設定用）

### 3) URL（ローカル）

- フロント（推奨）:  
  `http://localhost/pinkclub-fanza/public/index.php`
- フロント（ルート経由）:  
  `http://localhost/pinkclub-fanza/`
- 管理ログイン:  
  `http://localhost/pinkclub-fanza/public/login0718.php`

### 4) 初期管理ログイン

- ユーザー名: `admin`
- パスワード: `password`

> `config.local.php` に `admin.password_hash` がある場合はそちらが優先されます。

---

## 初期セットアップ手順

1. 管理ログイン（`/public/login0718.php`）
2. `DB初期化`（`/public/admin/db_init.php`）
3. `API設定`（`/public/admin/settings.php`）
4. `インポート`（`/public/admin/import_items.php`）

---

## 真っ白防止の仕様

フロントページは `public/_bootstrap.php` を通して初期化されます。

- DB接続失敗 / テーブル未準備 / 致命エラー時に **案内ページ or エラーページ** を表示
- 開発時（`app.env=development` または `app.debug=true`）は詳細エラー表示
- 例外は `logs/app.log` へ記録
- 致命系は HTTP 500 で返却

---

## パスワード再設定（メールなし運用）

1. `config.local.php` に `admin_reset_token` を設定
2. `http://localhost/pinkclub-fanza/public/forgot_password.php` を確認
3. `http://localhost/pinkclub-fanza/public/reset_password.php?token=設定したトークン` にアクセス
4. 新しいパスワードを入力して更新

- トークン不一致・未設定時は 403
- トークンは十分長いランダム文字列を推奨

---

## 本番運用の推奨

- 可能なら Web サーバの **DocumentRoot を `/public`** に向ける
- `config.local.php` は Git に含めない
- `logs/` は書き込み権限を付与

---

## 動作確認手順（実装後チェック用）

1. `config.local.php` を一時的に未設定にしてフロントへアクセスし、真っ白ではなく案内画面が表示されることを確認
2. DB設定を戻し、`/public/index.php` が通常表示されることを確認
3. `/public/login0718.php` で `admin / password` ログイン確認
4. ログイン後、強制的にパスワード変更へ飛ばないことを確認
5. ログイン画面の「パスワードを忘れた方はコチラ」から再設定導線を確認
6. 管理画面で左250pxサイドメニュー + カテゴリ分け表示を確認

