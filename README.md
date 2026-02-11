# PinkClub-FANZA

FANZA（DMM）APIから取得してDBへ保存した作品データを、**生PHPのSSR**で表示するサイトです。

## 現在できること

- 管理画面ログイン（CSRF保護）
- 管理画面からAPI設定保存
- `import_items.php` によるDMM API取得とDB upsert
- フロント表示（すべてDB実データ）
  - `/public/index.php`：新着 / ピックアップ / 女優 / シリーズ / メーカー / ジャンル
  - `/public/posts.php`：作品一覧（検索・並び替え・件数・ページング）
  - `/public/item.php?cid=...`：作品詳細（タクソノミ、サンプル画像、関連作品）
  - タクソノミ一覧と詳細
    - 女優 `/public/actresses.php` `/public/actress.php?id=...`
    - ジャンル `/public/genres.php` `/public/genre.php?id=...`
    - メーカー `/public/makers.php` `/public/maker.php?id=...`
    - シリーズ `/public/series.php` `/public/series_one.php?id=...`
- 404ページ
- basic SEO（title / description / canonical / OGP）

## 未実装（README上の旧構想から整理）

以下は現時点では**未実装**です。

- PV/UU解析
- 相互リンク管理
- RSS取得と表示
- GA4/Search Console連携
- 固定ページCMS
- sitemap/robots自動生成

## セキュリティ方針

- DBアクセスはPDOプリペアドステートメント
- 出力は `e()` でエスケープ
- 管理画面は既存のCSRF実装を維持

## セットアップ（GitHub/Codespaces想定）

1. `config.local.php.example` を `config.local.php` にコピー
2. DB接続情報を設定
3. `/public/admin/db_init.php` でテーブル作成
4. `/public/admin/settings.php` でAPI設定保存
5. `/public/admin/import_items.php` でデータ取得

> `config.local.php` は機密情報を含むためコミットしないでください。
