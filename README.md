# PinkClub FANZA (DMM/FANZA Affiliate API v3)

プレーンPHP + MySQL（XAMPP想定）で構築した、FANZA同期＆閲覧サイトです。

## 前提環境
- XAMPP（Apache + MySQL/MariaDB）
- PHP 8.1+ 推奨
- MySQL / MariaDB

## セットアップ手順（XAMPP）
1. このリポジトリを `C:\xampp\htdocs\pinkclub-fanza` に配置
2. XAMPPで Apache / MySQL を起動
3. phpMyAdmin で `pinkclub_fanza` を作成（utf8mb4推奨）
4. `sql/schema.sql` を実行
5. `sql/seed.sql` を実行
6. `config/config.php` を編集（DB接続やBASE_URL）
7. ブラウザでログインURLへアクセス

## ログイン情報
- URL: `http://localhost/pinkclub-fanza/public/login0718.php`
- ID: `admin`
- PW: `password`
- 初回ログイン後にパスワード変更推奨

## 同期手順
1. 管理画面ログイン
2. `API設定` で `api_id` / `affiliate_id` を保存
3. 接続テスト（API疎通）
4. Floor同期
5. マスタ同期（女優・ジャンル・メーカー・シリーズ・作者）
6. 商品同期（例: service=digital, floor=videoa）

## 構成（主要）
- `public/login0718.php` : 管理ログイン入口（固定）
- `public/_bootstrap.php` : 共通bootstrap
- `admin/*.php` : 管理画面
- `public/*.php` : 公開画面
- `lib/dmm_api_client.php` : APIクライアント
- `lib/dmm_normalizer.php` : APIレスポンス正規化
- `lib/dmm_sync_service.php` : 同期処理
- `sql/schema.sql`, `sql/seed.sql` : DB初期化

## セキュリティ対応
- PDO + prepared statement
- CSRFトークン検証（POSTフォーム）
- XSS対策 `e()`
- ログイン成功時 `session_regenerate_id(true)`
- 未ログインの admin 配下は `public/login0718.php` へリダイレクト

## トラブルシュート
- API接続エラー
  - API ID / Affiliate ID が正しいか
  - XAMPPのPHPで cURL 有効か
  - outbound通信がブロックされていないか
- MySQLが起動しない
  - 3306ポート競合を確認
  - XAMPP管理画面のログを確認
- ログインできない
  - `sql/seed.sql` 実行済みか
  - DB接続情報（`config/config.php`）を確認
