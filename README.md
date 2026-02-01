# TaniyanR-PinkClub-F
FANZA（DMM）API連携・自動記事生成対応の生PHP製アダルト向けCMS “PinkClub-F”。

PinkClub-F は FANZA（DMM）API から作品データを取得し、自動記事化＋内部回遊＋相互リンク＋RSS＋アクセス解析により集客を最適化する個人開発向けCMSです。  
技術的制約により Cron を利用せず、内部タイマー方式でAPI取得を制御します。

---

## Features

- FANZA（DMM）API対応
- 自動記事生成
- 自動タグ生成
- 関連記事自動表示
- 内部リンク最適化
- キーワードフィルタ（含む/除外）
- 相互リンク申請＋承認＋IN/OUT計測
- RSSキャッシュ＋表示
- 逆アクセスランキング
- 人気ランキング
- PV/UU解析
- 検索流入解析
- 固定ページ管理（特商法/プライバシー等）
- GA4 / Search Console 対応
- DMM APIエラーフェイルセーフ

---

## API Integration (FANZA / DMM)

- 取得間隔：1h / 3h / 6h / 12h / 24h
- 取得件数：10 / 100 / 500 / 1000
- Cron禁止 → 内部タイマー方式
- ロック・キャッシュ・フェイルセーフ実装
- 失敗時は72hキャッシュ保持
- 連続5回失敗で管理画面警告

取得項目例：

- 商品ID
- タイトル
- URL（アフィ付）
- サンプル画像 / サンプル動画
- 出演者 / ジャンル / シリーズ
- メーカー / レーベル
- 発売日 / 価格
- 説明文 / サムネイル

---

## Content Model

- 記事（Article）
- 女優（Actress）
- シリーズ（Series）
- メーカー（Maker）
- レーベル（Label）
- タグ（Tag）

※ Article → タクソノミ形式で関連付け  
※ タグは最大10自動生成

---

## Keyword Filter

含むキーワード：

- 最大5枠
- 各枠最大3ワード
- ワードはOR、枠もOR
- 未設定時は全文件許可

除外キーワード：

- 最大5ワード
- 1ワードヒットで除外

判定対象：

- タイトル / 説明文 / 出演者 / ジャンル / シリーズ / メーカー / レーベル

---

## Internal Linking

- 関連記事自動
- タクソノミ経由の回遊
- タグ経由内部回遊
- 「新着 / 人気 / おすすめ」の内部表示

---

## Reciprocal Links (相互リンク)

外部ユーザー申請 → 管理者承認方式

機能内容：

- サイト名 / URL / RSS 登録
- 承認/非表示管理
- 表示位置指定（PC/スマホ別）
- RSSキャッシュ
- IN/OUT計測
- 逆アクセスランキング

表示位置：

**PC**
- サイド
- リンク集ページ
- RSS：本文トップ / サイド / 本文ボトム

**SP**
- リンク：トップ
- RSS：ボトム

---

## Access Analytics

記録：

- PV / UU
- リンク元
- クリック先（OUT）
- 検索流入
- ボット判定（最低限UA）

集計：

- 時間 / 日 / 月
- 人気ランキング
- 逆アクセスランキング

---

## Fixed Pages

管理画面で編集可能：

- プライバシーポリシー
- 特商法
- 使い方
- お問い合わせ
- 追加/削除可能

---

## Admin Panel

項目：

- Dashboard
- 記事管理
- タクソノミ管理（女優/シリーズ/メーカー/レーベル）
- 相互リンク管理
- API設定
- アクセス解析
- 人気/逆アクセス
- デザイン設定
- コード挿入（広告枠）
- メール
- Backup
- GA4 / Search Console
- アカウント設定
- Logout

ログイン：

- メール＋パス
- ログイン中表示（インジケータ）

---

## SEO / Security

- canonical
- meta title / description / OGP
- alt自動付与
- パンくず（構造化データ）
- sitemap.xml
- robots.txt
- HTTPS必須
- CSRF / XSS / Clickjacking対策
- セッション管理
- PDO + Prepared必須

---

## Technology Stack

- PHP 8.x（Frameworkなし）
- MySQL 8.x
- PDO
- Vanilla JS
- UTF-8
- Asia/Tokyo
- Cron禁止（内部タイマー方式）

外部ライブラリ：

- 原則なし

---

## Development Status

仕様確定済み  
実装準備中

---

## Setup (最小手順)

1. MySQL で `pinkclub_f` データベースを作成する。
2. `sql/schema.sql` を実行してテーブルを作成する。
3. `config.local.php` を用意し、DB接続情報を設定する。
4. 管理画面にログインする。
5. 管理画面の API設定で FANZA/DMM API を保存する。
6. 管理画面のインポート機能を実行する。
7. 必要に応じて `php scripts/import.php` で手動インポートする。
8. `public/index.php` にアクセスして記事一覧を確認する。
9. `public/article.php?id=1` で記事詳細を確認する。
10. 取得結果が表示されればセットアップ完了。

---

## License

TBD
