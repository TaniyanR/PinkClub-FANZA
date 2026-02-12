# PinkClub-FANZA

生PHP（SSR）/ MySQL（PDO）で構成した FANZA系データ表示サイト + 管理画面です。  
本リポジトリは **フロントの真っ白防止** と **管理UIの整理** を含む最新版です。

---

## 今ある機能

- フロント表示（トップ / 投稿一覧 / 作品詳細 / 女優 / シリーズ / メーカー / ジャンル）
- 管理ログイン（入口: `public/login0718.php` / 管理トップ: `public/admin/index.php`）
- DB初期化、API設定、データインポート
- 管理ダッシュボードの状態カード（DB接続/テーブル初期化/API設定/作品件数/最終インポート）
- 管理パスワード変更（任意）
- パスワード再設定（`admin_reset_token` を使うトークン方式）
- フロントの共通ブートストラップ（DB未設定時に案内画面表示、真っ白防止）

## これからの機能（準備中）

- 相互リンク管理 / メール / RSS / アクセス解析 / バックアップ
- 固定ページCMS / SEO / 広告コード / アカウント設定 / API履歴 / デザイン設定
- いずれも管理メニューから「準備中」ページに遷移し、白画面になりません

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


## 管理ダッシュボードの状態カード

- **DB接続**: DB接続が成功すると `OK`、失敗時は `NG`（詳細は `logs/app.log`）
- **テーブル初期化**: 必須テーブルが揃うと `実施`、不足があると `未実施（不足: ...）`
- **API設定**: `dmm_api.api_id` と `dmm_api.affiliate_id` の両方が入ると `設定済`
- **作品件数**: `items` テーブルの件数を実測表示
- **最終インポート**: `api_logs.created_at`（成功ログ）または `items.updated_at` の最大値

### 状態カードが NG / 未実施 のときにやること

1. `DB接続: NG` → `config.local.php` のDB設定を確認
2. `テーブル初期化: 未実施` → `/public/admin/db_init.php` を実行
3. `API設定: 未設定` → `/public/admin/settings.php` で API ID / Affiliate ID を保存
4. `最終インポート: 未実施` → `/public/admin/import_items.php` でインポート実行

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

## 新機能（実装済み）

### 1. API履歴画面
管理画面から「API履歴」を選択すると、DMM API の実行履歴を確認できます。
- 日時、エンドポイント、成功/失敗ステータス、取得件数、エラーメッセージを表示
- ページネーション対応（50件ずつ表示）

### 2. 人気指標機能
- トップページの「ピックアップ」セクションで人気順（閲覧数順）にソート
- `items` テーブルに `view_count` カラムを追加
- `page_views` テーブルのデータを集計して人気スコアを計算

### 3. API自動取得タイマー機能
cronから実行可能な自動インポートスクリプトを追加しました。

#### cronの設定例

```bash
# 毎時0分に実行
0 * * * * /usr/bin/php /path/to/PinkClub-FANZA/scripts/auto_import.php >> /path/to/logs/cron.log 2>&1

# 3時間ごとに実行
0 */3 * * * /usr/bin/php /path/to/PinkClub-FANZA/scripts/auto_import.php >> /path/to/logs/cron.log 2>&1

# 1日1回（午前3時）に実行
0 3 * * * /usr/bin/php /path/to/PinkClub-FANZA/scripts/auto_import.php >> /path/to/logs/cron.log 2>&1
```

#### 手動実行

```bash
php /path/to/PinkClub-FANZA/scripts/auto_import.php
```

- ロック機構で重複実行を防止
- `api_schedules` テーブルで最終実行日時と次回実行予定を管理
- 実行履歴は `api_logs` テーブルに記録

### 4. API保存整形・重複排除
- 既存データとの重複チェック（`content_id` ベース）
- upsert ロジックで最新データのみ反映
- キャッシュ管理（72時間 = 3日間、`lib/dmm_api.php` で実装済み）

### 5. 自動タグ生成（基本版）
- タイトルやカテゴリーから自動的にタグを抽出
- `tags` および `item_tags` テーブルで多対多リレーション
- シンプルなキーワードマッチングでタグ生成

### 6. 関連記事抽出（基本版）
- 同じジャンル・女優・シリーズから関連作品を取得
- スコアリング: 女優一致 x5、シリーズ一致 x4、ジャンル一致 x3
- 不足時は新着で補填
- 作品詳細ページに関連作品セクションを表示

---

## データベースマイグレーション

新規セットアップの場合は `sql/schema.sql` が使用されますが、既存のデータベースに新機能を追加する場合は以下のマイグレーションを実行してください。

```bash
mysql -u your_user -p your_database < sql/migrations/001_add_incomplete_features.sql
```

このマイグレーションは以下を実行します：
- `items` テーブルに `view_count` カラムとインデックスを追加
- `api_logs`, `dmm_api`, `api_schedules` テーブルを作成
- `tags`, `item_tags` テーブルを作成
- `access_events` テーブルを作成
- デフォルトのスケジュール設定を挿入

---

## 動作確認手順（実装後チェック用）

### 基本機能の確認
1. `config.local.php` を一時的に未設定にしてフロントへアクセスし、真っ白ではなく案内画面が表示されることを確認
2. DB設定を戻し、`/public/index.php` が通常表示されることを確認
3. `/public/login0718.php` で `admin / password` ログイン確認
4. ログイン後、強制的にパスワード変更へ飛ばないことを確認
5. ログイン画面の「パスワードを忘れた方はコチラ」から再設定導線を確認
6. 管理画面で左250pxサイドメニュー + カテゴリ分け表示を確認

### 新機能の確認
7. 管理画面から「API履歴」を開き、履歴が正常に表示されることを確認
8. インポートを実行後、「API履歴」に新しいログが追加されることを確認
9. トップページの「ピックアップ」セクションが「人気順」で表示されることを確認
10. 作品詳細ページで関連作品が表示されることを確認
11. 自動インポートスクリプト（`scripts/auto_import.php`）を手動実行し、正常に動作することを確認
12. データベースマイグレーションが正常に適用されることを確認

