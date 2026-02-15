# 要件①〜㊷ Issue分解（TaniyanR/PinkClub-FANZA）

このドキュメントは、ユーザー要件①〜㊷を **1 Issue = 1 PR** で段階実装するためのGitHub Issues原案です。  
現行READMEの実装済み機能を踏まえ、`status` を `todo` / `verify` で付与しています。

- `todo` : 未実装または要再実装
- `verify` : 実装済みの可能性が高く、受け入れ確認中心

---

## 1) Issue一覧（番号、タイトル、priority、epic、依存）

| No | Title | status | Priority | Epic | Type | Depends on |
|---|---|---|---|---|---|---|
| ① | DB設計最終化（PK/FK/INDEX/分析・RSS・リンク対応） | todo | P0 | epic:foundation | type:feature | - |
| ② | config.php/config.local.php 切替整備（直書き排除） | verify | P0 | epic:foundation | type:chore | ① |
| ③ | APIキー管理の秘匿化・テストキー切替 | verify | P0 | epic:import | type:security | ② |
| ④ | API取得内部タイマー（last_run/interval/lock_until） | todo | P0 | epic:import | type:feature | ①,③ |
| ⑤ | API保存整形（重複排除・更新反映・72hキャッシュ） | todo | P0 | epic:import | type:feature | ①,④ |
| ⑥ | 自動タグ生成 | todo | P1 | epic:front | type:feature | ⑤ |
| ⑦ | キーワードフィルタ | todo | P1 | epic:front | type:feature | ⑤ |
| ⑧ | 関連記事抽出（スコア+新着補填） | todo | P1 | epic:front | type:feature | ⑤,⑥,⑦ |
| ⑨ | 記事テンプレ最適化（meta/OGP/canonical/alt/SNS） | verify | P1 | epic:seo | type:feature | ⑤ |
| ⑩ | タクソノミ出力（カテゴリ軸） | verify | P1 | epic:front | type:feature | ⑤ |
| ⑪ | 相互リンク申請フォーム | verify | P1 | epic:links | type:feature | ① |
| ⑫ | 相互リンク承認フロー（PC/SP位置、RSS ON/OFF） | todo | P1 | epic:links | type:feature | ⑪ |
| ⑬ | IN/OUT計測（from, 中間302, access_events記録） | todo | P1 | epic:analytics | type:feature | ①,⑪ |
| ⑭ | RSSキャッシュ（RSS→JSON、失敗時維持） | verify | P1 | epic:rss | type:feature | ① |
| ⑮ | 逆アクセスランキング（IN降順、表示ON/OFF） | todo | P1 | epic:links | type:feature | ⑬ |
| ⑯ | アクセスログ（PV/UU/referrer/OUT/search/bot） | verify | P1 | epic:analytics | type:feature | ① |
| ⑰ | 時間/日/月集計と軽量分析 | verify | P1 | epic:analytics | type:feature | ⑯ |
| ⑱ | 人気ランキング（PV/UU順） | verify | P1 | epic:analytics | type:feature | ⑰ |
| ⑲ | 固定ページCMS（特商法/プライバシー等CRUD） | verify | P1 | epic:admin | type:feature | ① |
| ⑳ | メール機能（管理画面内送受信/通知先/双方向） | todo | P2 | epic:ops | type:feature | ② |
| ㉑ | GA4/GSC連携（管理設定→head挿入） | verify | P1 | epic:seo | type:feature | ② |
| ㉒ | 任意コード挿入枠（PC/SP配置） | verify | P1 | epic:admin | type:feature | ② |
| ㉓ | サイト設定（site_name/SEO/表示ONOFF反映） | verify | P1 | epic:admin | type:feature | ② |
| ㉔ | 管理ログイン仕様固定（/public/login0718.php, admin/password） | verify | P0 | epic:admin | type:security | ②,㊲ |
| ㉕ | 管理メニュー順固定 + サイドバー250px | verify | P1 | epic:admin | type:feature | ㉔ |
| ㉖ | 一覧カードUI（回遊導線） | verify | P1 | epic:front | type:feature | ⑤ |
| ㉗ | 個別表示UI（回遊導線） | verify | P1 | epic:front | type:feature | ⑤,⑧ |
| ㉘ | タクソノミ0件時挙動（404/空表示方針） | todo | P1 | epic:front | type:bug | ⑩ |
| ㉙ | RSS表示位置制御（PC本文上/側/下、SP下部） | todo | P1 | epic:rss | type:feature | ⑭ |
| ㉚ | 相互リンク表示位置制御（PC側・SPトップ、ON/OFF） | todo | P1 | epic:links | type:feature | ⑫,⑮ |
| ㉛ | 検索（タイトル/説明のみ） | verify | P1 | epic:front | type:feature | ⑤ |
| ㉜ | 画像処理（800x600比率維持、代替画像） | todo | P1 | epic:front | type:feature | ⑤ |
| ㉝ | サンプル画像ビュー（横スクロール/別窓） | todo | P1 | epic:front | type:feature | ⑤,㉜ |
| ㉞ | サンプル動画（video controls preload=none） | todo | P1 | epic:front | type:feature | ⑤ |
| ㉟ | SEO最適化（パンくず構造化/OGP/canonical） | verify | P1 | epic:seo | type:feature | ⑨ |
| ㊱ | sitemap.xml / robots.txt（admin拒否） | verify | P1 | epic:seo | type:feature | ㉓ |
| ㊲ | セキュリティ統合（CSRF/XSS/Clickjacking/Session） | verify | P0 | epic:foundation | type:security | ② |
| ㊳ | バックアップ（DBエクスポートDL） | verify | P2 | epic:ops | type:feature | ① |
| ㊴ | API失敗5回警告（管理画面） | todo | P1 | epic:analytics | type:feature | ④ |
| ㊵ | テストデータ投入（API不要確認） | todo | P2 | epic:ops | type:chore | ①,⑤ |
| ㊶ | 整合性チェック（DB/API/フロント/管理） | todo | P2 | epic:ops | type:chore | ①〜㊵ |
| ㊷ | 本番調整（間隔/表示ONOFF/SEO/解析ONOFF） | todo | P2 | epic:ops | type:chore | ㊶ |

---

## 2) ラベル設計

- Epic系：
  - `epic:foundation`
  - `epic:import`
  - `epic:seo`
  - `epic:analytics`
  - `epic:links`
  - `epic:rss`
  - `epic:admin`
  - `epic:front`
  - `epic:ops`
- Type系：`type:feature`, `type:chore`, `type:security`, `type:bug`
- Priority系：`priority:P0`, `priority:P1`, `priority:P2`

---

## 3) GitHubに貼れるIssue本文テンプレ（各Issue共通）

```md
## 背景
- 要件番号: （例: ④）
- 目的: （何を満たすか）

## スコープ
- [ ] 実装
- [ ] 管理画面反映（必要時）
- [ ] ドキュメント更新（README）
- [ ] 後方互換性確認

## 受け入れ基準
- [ ] 仕様を満たす
- [ ] 既存機能を壊さない
- [ ] PHP8.x + MySQL8.x + PDOで動作
- [ ] SQLはプリペアド
- [ ] created_at / updated_at が必要テーブルに存在

## セキュリティ/運用
- [ ] CSRF/XSS/Session確認
- [ ] config.local.php はコミットしない
- [ ] UTF-8 / Asia/Tokyoで動作

## 動作確認手順
1. URL:
2. 操作:
3. 期待結果:

## 備考
- 依存Issue:
```

---

## 4) 各Issue本文（GitHubコピペ用）

### ① DB設計最終化（PK/FK/INDEX/分析・RSS・リンク対応）
```md
## 背景
土台となるDB設計を固定し、以降の実装での手戻りを防ぐ。

## スコープ
- `sql/schema.sql` を正とする
- PK/FK/INDEX最適化
- 対象: taxonomy / posts / api_history / rss_cache / reciprocal_links / access_events ほか
- `created_at` / `updated_at` 原則付与

## 受け入れ基準
- EXPLAINで主要JOINが実用的なコスト
- 既存データ移行手順（手動SQL）をPR本文に記載

## ラベル
`epic:foundation` `type:feature` `priority:P0`
```

### ② config.php/config.local.php 切替整備（直書き排除）
```md
## 背景
機密・環境差分を安全に扱うため、設定の読み込み方針を統一する。

## スコープ
- `config.php` + `config.local.php` 優先ルールの確認/統一
- 直書き秘密情報の除去
- `config.local.php` を任意作成で上書きできるよう整理

## 受け入れ基準
- 本番/開発の切替がファイル差分のみで可能
- `config.local.php` をGit管理しない

## ラベル
`epic:foundation` `type:chore` `priority:P0`
```

### ③ APIキー管理の秘匿化・テストキー切替
```md
## 背景
APIキー漏えい防止と運用性向上。

## スコープ
- 管理画面で平文表示しない
- テストキー切替導線
- 保存先ポリシー明確化（config/local or DB）

## 受け入れ基準
- 画面表示でキー復元不可
- 既存設定との後方互換あり

## ラベル
`epic:import` `type:security` `priority:P0`
```

### ④ API取得内部タイマー（last_run/interval/lock_until）
```md
## 背景
cron禁止のため、内部タイマーで安全に定期取得する。

## スコープ
- `last_run` / `interval` / `lock_until` 管理
- 多重実行防止
- 間隔: 1/3/6/12/24h
- 件数: 10/100/500/1000
- 失敗連続5回で警告フラグ

## 受け入れ基準
- 同時アクセスでも二重取得しない
- 5連続失敗時に管理UIで警告可能

## ラベル
`epic:import` `type:feature` `priority:P0`
```

### ⑤ API保存整形（重複排除・更新反映・72hキャッシュ）
```md
## 背景
取得データをフロント表示に使える形へ正規化する。

## スコープ
- 重複排除（キー: content_id等）
- 更新反映（upsert）
- 画像/動画/出演者/シリーズ/メーカー/レーベル整形
- 72hキャッシュ方針

## 受け入れ基準
- 同一作品が二重作成されない
- 更新データが正しく反映

## ラベル
`epic:import` `type:feature` `priority:P0`
```

### ⑥ 自動タグ生成
```md
## 背景
回遊性改善のため、作品情報からタグを自動生成する。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ⑦ キーワードフィルタ
```md
## 背景
不要語を除外し品質を上げる。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ⑧ 関連記事抽出（スコア+新着補填）
```md
## 背景
関連記事を機械抽出し回遊率を高める。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ⑨ 記事テンプレ最適化（meta/OGP/canonical/alt/SNS）
```md
## 背景
SEO/SNS表示品質を統一する。

## ラベル
`epic:seo` `type:feature` `priority:P1`
```

### ⑩ タクソノミ出力（カテゴリ軸）
```md
## 背景
カテゴリ軸の一覧導線を標準化する。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ⑪ 相互リンク申請フォーム
```md
## 背景
外部サイトからの相互リンク申請を受け付ける。

## ラベル
`epic:links` `type:feature` `priority:P1`
```

### ⑫ 相互リンク承認フロー（PC/SP位置、RSS ON/OFF）
```md
## 背景
申請後の承認と表示制御を管理画面で行えるようにする。

## ラベル
`epic:links` `type:feature` `priority:P1`
```

### ⑬ IN/OUT計測（from, 中間302, access_events記録）
```md
## 背景
リンク流入・流出を正確に計測する。

## ラベル
`epic:analytics` `type:feature` `priority:P1`
```

### ⑭ RSSキャッシュ（RSS→JSON、失敗時維持）
```md
## 背景
外部RSSの取得失敗時でも表示を安定させる。

## ラベル
`epic:rss` `type:feature` `priority:P1`
```

### ⑮ 逆アクセスランキング（IN降順、表示ON/OFF）
```md
## 背景
逆アクセス順でのランキング表示に対応する。

## ラベル
`epic:links` `type:feature` `priority:P1`
```

### ⑯ アクセスログ（PV/UU/referrer/OUT/search/bot）
```md
## 背景
運用判断に必要なイベントログを収集する。

## ラベル
`epic:analytics` `type:feature` `priority:P1`
```

### ⑰ 時間/日/月集計と軽量分析
```md
## 背景
アクセスログを集計し管理画面可視化する。

## ラベル
`epic:analytics` `type:feature` `priority:P1`
```

### ⑱ 人気ランキング（PV/UU順）
```md
## 背景
人気コンテンツを指標化して表示する。

## ラベル
`epic:analytics` `type:feature` `priority:P1`
```

### ⑲ 固定ページCMS（特商法/プライバシー等CRUD）
```md
## 背景
法務・運営ページを管理画面から更新可能にする。

## ラベル
`epic:admin` `type:feature` `priority:P1`
```

### ⑳ メール機能（管理画面内送受信/通知先/双方向）
```md
## 背景
問い合わせ運用を管理画面で完結させる。

## ラベル
`epic:ops` `type:feature` `priority:P2`
```

### ㉑ GA4/GSC連携（管理設定→head挿入）
```md
## 背景
解析タグとGSC所有確認を設定画面から管理可能にする。

## ラベル
`epic:seo` `type:feature` `priority:P1`
```

### ㉒ 任意コード挿入枠（PC/SP配置）
```md
## 背景
広告や外部コードを位置指定で挿入可能にする。

## ラベル
`epic:admin` `type:feature` `priority:P1`
```

### ㉓ サイト設定（site_name/SEO/表示ONOFF反映）
```md
## 背景
サイト全体設定を管理画面から変更可能にする。

## ラベル
`epic:admin` `type:feature` `priority:P1`
```

### ㉔ 管理ログイン仕様固定（/public/login0718.php, admin/password）
```md
## 背景
管理ログイン要件を固定し、初期利用の混乱を防ぐ。

## 必須仕様
- ログインURL: `/public/login0718.php`
- 初期資格情報: `admin / password`
- パスワード変更は任意（強制しない）
- `config.local.php` の `admin.password_hash` が存在すれば優先
- 管理画面にパスワード変更導線（アカウント設定）

## ラベル
`epic:admin` `type:security` `priority:P0`
```

### ㉕ 管理メニュー順固定 + サイドバー250px
```md
## 背景
WordPress風の一貫UIに合わせる。

## 必須仕様
- サイドメニュー固定幅250px
- 上部バー: フロント表示 / ログアウト / ログイン中ユーザー
- ダッシュボードをカード表示化

## ラベル
`epic:admin` `type:feature` `priority:P1`
```

### ㉖ 一覧カードUI（回遊導線）
```md
## 背景
一覧ページの視認性・回遊性を高める。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ㉗ 個別表示UI（回遊導線）
```md
## 背景
個別ページの回遊導線を標準化する。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ㉘ タクソノミ0件時挙動（404/空表示方針）
```md
## 背景
0件時にUXを壊さない挙動を統一する。

## ラベル
`epic:front` `type:bug` `priority:P1`
```

### ㉙ RSS表示位置制御（PC本文上/側/下、SP下部）
```md
## 背景
RSSブロックの表示位置を設定可能にする。

## ラベル
`epic:rss` `type:feature` `priority:P1`
```

### ㉚ 相互リンク表示位置制御（PC側・SPトップ、ON/OFF）
```md
## 背景
相互リンク表示を媒体別に制御可能にする。

## ラベル
`epic:links` `type:feature` `priority:P1`
```

### ㉛ 検索（タイトル/説明のみ）
```md
## 背景
検索対象を限定して精度を担保する。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ㉜ 画像処理（800x600比率維持、代替画像）
```md
## 背景
画像崩れを防ぎ統一感を維持する。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ㉝ サンプル画像ビュー（横スクロール/別窓）
```md
## 背景
サンプル画像閲覧体験を改善する。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ㉞ サンプル動画（video controls preload=none）
```md
## 背景
帯域負荷を抑えつつ動画確認を可能にする。

## ラベル
`epic:front` `type:feature` `priority:P1`
```

### ㉟ SEO最適化（パンくず構造化/OGP/canonical）
```md
## 背景
検索エンジンとSNS双方の品質を高める。

## ラベル
`epic:seo` `type:feature` `priority:P1`
```

### ㊱ sitemap.xml / robots.txt（admin拒否）
```md
## 背景
クロール最適化と管理領域保護を両立する。

## ラベル
`epic:seo` `type:feature` `priority:P1`
```

### ㊲ セキュリティ統合（CSRF/XSS/Clickjacking/Session）
```md
## 背景
管理/公開双方で最低限のWebセキュリティを担保する。

## ラベル
`epic:foundation` `type:security` `priority:P0`
```

### ㊳ バックアップ（DBエクスポートDL）
```md
## 背景
障害時復旧を可能にする。

## ラベル
`epic:ops` `type:feature` `priority:P2`
```

### ㊴ API失敗5回警告（管理画面）
```md
## 背景
外部API障害に気付きやすくする。

## ラベル
`epic:analytics` `type:feature` `priority:P1`
```

### ㊵ テストデータ投入（API不要確認）
```md
## 背景
外部依存なしで画面確認可能にする。

## ラベル
`epic:ops` `type:chore` `priority:P2`
```

### ㊶ 整合性チェック（DB/API/フロント/管理）
```md
## 背景
機能間の接続不整合を最終検証する。

## ラベル
`epic:ops` `type:chore` `priority:P2`
```

### ㊷ 本番調整（間隔/表示ONOFF/SEO/解析ONOFF）
```md
## 背景
運用開始前の最終チューニングを実施する。

## ラベル
`epic:ops` `type:chore` `priority:P2`
```

---

## 5) 実装順序（段階PR）

1. Phase 0（①②③④⑤㊲㉔）
2. Phase 1（⑥⑦⑧⑨⑩㉘㉛㉜㉝㉞㉖㉗）
3. Phase 2（⑪⑫⑬⑮㉚）
4. Phase 3（⑭㉙）
5. Phase 4（⑯⑰⑱㊴）
6. Phase 5（⑲）
7. Phase 6（㉟㊱㉑）
8. Phase 7（㉒㉓㉕）
9. Phase 8（⑳㊳㊵㊶㊷）

