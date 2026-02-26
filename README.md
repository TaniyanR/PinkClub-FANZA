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
2. 画面表示時に DB 自動セットアップ（DB作成 → schema.sql → seed.sql → admin保証）が自動実行される
3. そのままログイン画面からサインインする

> セットアップ状況は `http://localhost/pinkclub-fanza/public/setup_check.php` で確認できます（ボタン操作不要）。

## 4. ログインURL（固定）
- 入口URLはこの1つのみです（他の `login*.php` は使わない）。
- `http://localhost/pinkclub-fanza/public/login0718.php`

## 5. 初期ID / PW
- ID: `admin`
- PW: `password`
- 初回ログイン後に必ず変更してください。

## 6. 自動セットアップ失敗時（手動フォールバック）
1. phpMyAdmin でデータベース `pinkclub_fanza` を作成
2. `sql/schema.sql` をインポート
3. 続けて `sql/seed.sql` をインポート

> インポート順は必ず `schema.sql` → `seed.sql` の順です。  
> 自動セットアップの失敗詳細は `logs/install.log` を確認してください（例外メッセージと失敗SQLを追記）。

## 7. CSSが効かないときの確認
1. 直接アクセスでCSSが見えるか確認
   - `http://localhost/pinkclub-fanza/assets/css/style.css`
2. `config/config.php` の `BASE_URL` 設定を確認
   - `http://localhost/pinkclub-fanza`
3. ログイン画面URLが固定URLになっているか確認
   - `http://localhost/pinkclub-fanza/public/login0718.php`

## 8. MySQL起動トラブルの注意（簡潔版）
- InnoDBログ不整合がある場合は、必ずバックアップを取得してから復旧してください。
- `xampp/mysql/data` をむやみに上書きしないでください。

## 9. 同期手順
1. 管理画面で API設定（`api_id` / `affiliate_id`）を保存
2. Floor同期
3. マスタ同期（Actress / Genre / Maker / Series / Author）
4. 商品同期（ItemList）

## 主要URL
- ログイン: `http://localhost/pinkclub-fanza/public/login0718.php`
- 管理画面トップ: `http://localhost/pinkclub-fanza/admin/index.php`
- 公開トップ: `http://localhost/pinkclub-fanza/public/index.php`
- 初期セットアップ確認: `http://localhost/pinkclub-fanza/public/setup_check.php`

## セキュリティ実装（現行）
- CSRF対策（トークン検証）
- XSS対策（`e()` エスケープ）
- パスワード検証（`password_hash` / `password_verify`）
- PDO Prepared Statement


失敗時の詳細は `logs/install.log` を確認してください。
