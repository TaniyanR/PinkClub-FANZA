# PinkClub FANZA（XAMPP運用ガイド）

PinkClub FANZA は、DMM/FANZA Affiliate API v3 と連携して商品データを同期・公開する PHP + MySQL（MariaDB）アプリです。

## 1. 配置場所
- `C:\xampp\htdocs\pinkclub-fanza` に配置してください。

## 2. 起動
1. XAMPP Control Panel を開く
2. `Apache` を `Start`
3. `MySQL` を `Start`

## 3. 初回セットアップ（自動）
1. ブラウザで `http://localhost/pinkclub-fanza/public/login0718.php` を開く
2. 画面表示時に DB 自動セットアップ（DB作成 → schema.sql → seed.sql試行 → admin/settings保証INSERT）が自動実行される
3. セットアップ成功時は `logs/install.lock` が作成され、次回以降は自動セットアップを再実行しない
4. そのままログイン画面からサインインする

> 自動セットアップは `localhost / 127.0.0.1` 限定です。  
> `setup_check.php` は失敗時の診断ページです（成功時はログイン画面へ戻ります）。

## 4. ログインURL（固定）
- 入口URLはこの1つのみです（他の `login*.php` は使わない）。
- `http://localhost/pinkclub-fanza/public/login0718.php`

## 5. 初期ID / PW
- ID: `admin`
- PW: `password`
- 初回ログイン後に必ず変更してください。

## 6. 自動セットアップ失敗時の確認
1. `http://localhost/pinkclub-fanza/public/setup_check.php` を開く
2. 以下を画面で確認する
   - 失敗ステップ
   - 例外クラス / メッセージ
   - 失敗SQL（取得できた場合）
   - `install.log` の末尾20行
3. 失敗原因修正後、必要なら `logs/install.lock` を削除して再アクセス

## 7. 自動セットアップ再実行方法
- `logs/install.lock` を削除すると、次回 `login0718.php` アクセス時に再セットアップされます。

## 8. localhost 制限
- 自動セットアップ実行は `localhost` / `127.0.0.1` / `::1` からのアクセス時のみ許可しています。
- それ以外のホストでは自動セットアップは実行されません（403）。

## 9. CSSが効かないときの確認
1. 直接アクセスでCSSが見えるか確認
   - `http://localhost/pinkclub-fanza/assets/css/style.css`
2. `config/config.php` の `BASE_URL` 設定を確認（末尾スラッシュ不要）
   - `http://localhost/pinkclub-fanza`
3. ログイン画面URLが固定URLになっているか確認
   - `http://localhost/pinkclub-fanza/public/login0718.php`

## 10. MySQL起動トラブルの注意（簡潔版）
- InnoDBログ不整合がある場合は、必ずバックアップを取得してから復旧してください。
- `xampp/mysql/data` をむやみに上書きしないでください。

## 11. 同期手順
1. 管理画面で API設定（`api_id` / `affiliate_id`）を保存
2. Floor同期
3. マスタ同期（Actress / Genre / Maker / Series / Author）
4. 商品同期（ItemList）

## 主要URL
- ログイン: `http://localhost/pinkclub-fanza/public/login0718.php`
- 管理画面トップ: `http://localhost/pinkclub-fanza/admin/index.php`
- 公開トップ: `http://localhost/pinkclub-fanza/public/index.php`
- 初期セットアップ確認: `http://localhost/pinkclub-fanza/public/setup_check.php`

## 手動確認手順（簡易）
1. `http://localhost/pinkclub-fanza/public/login0718.php` にアクセス
2. ログイン画面がCSS付きで表示されることを確認
3. `admin` / `password` でログイン
4. `http://localhost/pinkclub-fanza/admin/index.php` に遷移できることを確認

## セキュリティ実装（現行）
- CSRF対策（トークン検証）
- XSS対策（`e()` エスケープ）
- パスワード検証（`password_hash` / `password_verify`）
- PDO Prepared Statement
## 管理画面の見える化（段階1）
- `public/admin/sitemap.php` と `admin/sitemap.php` で、検出した管理ページ一覧（パス/ラベル/状態/認証/存在確認）を確認できます。
- サイドメニューはディレクトリスキャンにより自動生成されます（`/public/admin` と `/admin` の両方）。
- `未整備` バッジ付きの項目は、導線用途としては残しているが画面遷移向け最適化が未完了の候補です。

## 整理・共通化メモ（段階2）
- 共通化: 管理ページ検出ロジックを `lib/admin_page_discovery.php` に集約。
- 削除候補の判定は sitemap を基準に、参照元確認と手動アクセス確認を行ってから実施してください。
- 本PRでは安全のため大量削除は未実施です（候補のみ可視化）。
