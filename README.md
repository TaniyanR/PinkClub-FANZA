# PinkClub FANZA 商品情報サイト

PHP + MySQL（PDO）で実装した、FANZA/DMMアフィリエイトAPI v3 ベースの商品情報サイトです。フレームワークは使わず、XAMPPでの運用を想定しています。

## ディレクトリ構成（実装済み）

- `config/` 設定・ルーティング
- `lib/` DB / 認証 / CSRF / APIクライアント / 正規化 / 同期サービス
- `admin/` 管理画面（ログイン、設定、同期、ダッシュボード）
- `public/` フロント画面（トップ、商品、女優、ジャンル、メーカー、シリーズ、作者）
- `sql/` `schema.sql` / `seed.sql`
- `assets/css/style.css` 最低限の共通デザイン

## セットアップ（XAMPP）

1. DB作成（例: `pinkclub_fanza`）
2. `sql/schema.sql` を実行
3. `sql/seed.sql` を実行
4. `config/config.php` のDB接続情報を環境に合わせて編集
5. Apacheの公開パス配下に配置してアクセス

## 初期ログイン情報

- username: `admin`
- password: `password`

> 初期値なので、ログイン後の変更を強く推奨します（seedは `password_hash()` で保存済み）。

## 管理画面

- `/admin/login.php` ログイン
- `/admin/settings.php` API設定、接続テスト、Floor同期、Item同期
- `/admin/sync_master.php` 女優/ジャンル/メーカー/シリーズ/作者 同期
- `/admin/sync_items.php` 条件付きItem同期
- `/admin/index.php` 件数サマリ、同期ログ

## 同期手順（重要）

**必ず次の順で同期してください。**

1. **Floor同期**（FloorList）
2. **マスタ同期**（女優/ジャンル/メーカー/シリーズ/作者）
3. **商品同期**（ItemList）

- ItemListだけでも同期は可能ですが、ID絞り込みや表示品質のためにマスタ同期を推奨します。
- 本実装はAPIレスポンスの形式ゆれ（単体/配列）を `lib/dmm_normalizer.php` で吸収しています。

## 実装仕様の要点

- APIクライアントは cURL + timeout + HTTP/JSON/API status 検証
- 接続テストと同期処理を分離
- UPSERT (`INSERT ... ON DUPLICATE KEY UPDATE`)
- Item同期はトランザクションで本体+中間+キャンペーン等を保存
- CSRF対策、XSS対策、管理画面認証、session fixation対策を実装

## よくあるエラー

- API ID / Affiliate ID未設定: 接続テスト・同期が失敗
- Floor未同期: `resolveFloorPair()` で例外（先にFloor同期を実行）
- DB文字コードが `utf8mb4` でない: 文字化けの原因
- FANZA API側の仕様揺れ: 正規化対象外のフィールド変化がある場合は `lib/dmm_normalizer.php` を拡張してください
