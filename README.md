# Kashiwazaki SEO Universal Sitemap

![Version](https://img.shields.io/badge/version-1.0.3-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)

柏崎剛によるユニバーサルSEOサイトマッププラグイン。投稿タイプ別のサイトマップ、画像・動画サイトマップ、Googleニュースサイトマップに対応。大規模サイト向けの自動分割機能とGZIP圧縮を搭載。

## 主な機能

### ✨ コア機能

- **自動分割機能**
  - 投稿タイプサイトマップ：50,000件ごとに自動分割
  - Googleニュースサイトマップ：1,000件ごとに自動分割
  - すべての分割ファイルを自動的にインデックスサイトマップに登録

- **GZIP圧縮対応**
  - ファイルサイズを約90-98%削減
  - 管理画面でON/OFF切替可能
  - Googlebot完全対応

- **画像・動画サイトマップ**
  - アイキャッチ画像と本文中の画像に対応
  - YouTube・Vimeo動画に対応
  - Google画像検索・動画検索最適化

- **Googleニュースサイトマップ**
  - 投稿タイプ選択式
  - 1,000件で自動分割（Google仕様準拠）
  - 最新記事優先

### 🎨 UI/UX

- **タブ形式の管理画面**
  - サイトマップ & 設定
  - 統計情報
  - 詳細な使い方ガイド

- **直感的な操作**
  - ニュースサイトマップセクションの折りたたみ
  - URLワンクリックコピー
  - 分割ファイルへの直接リンク

- **HTMLヘッダー出力**
  - `<link rel="sitemap">`を自動追加
  - ON/OFF切替可能

## インストール

1. このリポジトリをダウンロードまたはクローン
2. `/wp-content/plugins/`ディレクトリにアップロード
3. WordPressの管理画面でプラグインを有効化
4. 自動的にサイトマップが生成されます

## 使い方

### 基本的な使い方

1. プラグインを有効化
2. `設定` → `パーマリンク設定` → `変更を保存`（リライトルールのフラッシュ）
3. 管理画面の「Kashiwazaki SEO Universal Sitemap」から設定
4. インデックスサイトマップURL（`/sitemap.xml`）をGoogle Search Consoleに登録

### 詳細設定

#### 投稿タイプの設定
- サイトマップに含める投稿タイプを選択
- 更新頻度（changefreq）を設定
- 優先度（priority）を設定（0.0〜1.0）

#### 画像・動画情報
- 画像情報のON/OFF
- 動画情報のON/OFF
- GZIP圧縮のON/OFF
- HTMLヘッダー出力のON/OFF

#### Googleニュースサイトマップ
- ニュースに掲載する投稿タイプを選択
- 自動的に1,000件で分割

### 個別投稿の除外

投稿編集画面の「Kashiwazaki SEO Universal Sitemap」メタボックスから、特定の投稿をサイトマップから除外できます。

## 技術仕様

### 自動分割機能

**投稿タイプサイトマップ：**
- 最初の50,000件：`sitemap-post.xml`
- 次の50,000件：`sitemap-post-2.xml`
- 以降：`sitemap-post-3.xml`, `sitemap-post-4.xml`...

**Googleニュースサイトマップ：**
- 最初の1,000件：`sitemap-googlenews.xml`
- 次の1,000件：`sitemap-googlenews-2.xml`
- 以降：`sitemap-googlenews-3.xml`...

### GZIP圧縮

- 有効時：すべてのサイトマップを`.xml.gz`形式で保存
- 無効時：`.xml`形式で保存
- URL：`.xml`でアクセス可能（透過的に`.xml.gz`を配信）
- ヘッダー：`Content-Encoding: gzip`

### 自動再生成

サイトマップは以下のタイミングで自動的に再生成されます：
- 設定保存時
- 投稿の公開・更新時
- 投稿の削除時

## 動作環境

- **WordPress**: 6.0以上
- **PHP**: 8.0以上
- **MySQL/MariaDB**: 5.7以上

## 更新履歴

最新の変更内容については [CHANGELOG.md](CHANGELOG.md) をご覧ください。

### v1.0.3 (2025-11-13)

- 自動分割機能追加：投稿50,000件、ニュース1,000件で自動分割
- GZIP圧縮対応：約90-98%のファイルサイズ削減
- タブ形式の管理画面：設定/統計/使い方を分離
- ニュースサイトマップセクションの折りたたみ機能
- HTMLヘッダー出力機能（ON/OFF切替可能）
- プラグイン一覧に「設定」リンク追加
- 詳細な使い方ガイドとトラブルシューティング追加
- UI/UX全般の大幅改善

### v1.0.2 (2025-10-23)

- カスタム投稿タイプのサイトマップがインデックスに正しく含まれるように修正
- サイトマップ生成順序を最適化

### v1.0.1 (2025-10-09)

- 画像情報のメタデータ取得ロジックを改善
- alt/title/caption の優先順位を最適化
- HTMLタグ除去とトリミング処理を強化
- 空文字列チェックをより厳密に変更

### v1.0.0 (2025-10-08)

- 初回リリース
- 投稿タイプ別サイトマップ生成機能
- ニュースサイトマップ、画像・動画情報の埋め込みに対応
- 個別投稿レベルでの制御機能

## ライセンス

GPL v2 or later

## 著者

**柏崎剛 (Tsuyoshi Kashiwazaki)**
- Website: [https://www.tsuyoshikashiwazaki.jp](https://www.tsuyoshikashiwazaki.jp)
- Profile: [https://www.tsuyoshikashiwazaki.jp/profile/](https://www.tsuyoshikashiwazaki.jp/profile/)

## サポート

問題や機能要望がある場合は、[GitHubのIssues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-universal-sitemap/issues)でご報告ください。
