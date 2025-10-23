# Kashiwazaki SEO Universal Sitemap

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.2--dev-orange.svg)](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-universal-sitemap/releases)

投稿タイプ別のXMLサイトマップを生成し、ニュースサイトマップ、画像・動画情報の埋め込みにも対応したSEO最適化プラグインです。

> **検索エンジン最適化のための包括的なサイトマップソリューション**

## 主な機能

- **投稿タイプ別サイトマップ生成** - 投稿、固定ページ、カスタム投稿タイプに対応
- **ニュースサイトマップ** - Google Newsに最適化されたサイトマップ
- **画像情報の埋め込み** - 画像検索最適化のための画像情報を投稿タイプ別サイトマップに埋め込み
- **動画情報の埋め込み** - YouTube・Vimeoの動画情報を投稿タイプ別サイトマップに埋め込み
- **柔軟な設定** - 各投稿タイプごとに細かく設定可能
- **自動更新** - コンテンツ更新時に自動的にサイトマップを再生成
- **投稿レベルのコントロール** - 個別の投稿でサイトマップへの含有を制御可能

## クイックスタート

### インストール

1. プラグインファイルを `/wp-content/plugins/kashiwazaki-seo-universal-sitemap/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 「設定」→「Kashiwazaki SEO Universal Sitemap」から設定を行う

### 基本設定

1. 管理画面でサイトマップに含める投稿タイプを選択
2. 各投稿タイプの優先度と更新頻度を設定
3. 必要に応じてニュースサイトマップ、画像・動画情報の埋め込みを有効化
4. 設定を保存してサイトマップを生成

### サイトマップURL

生成されたサイトマップは以下のURLでアクセスできます：

- **インデックスサイトマップ**: `https://yourdomain.com/sitemap.xml`
- **投稿タイプ別サイトマップ**: `https://yourdomain.com/sitemap-{post_type}.xml`
  （例: `sitemap-post.xml`、`sitemap-page.xml`）
  ※ 画像・動画情報は投稿タイプ別サイトマップ内に含まれます
- **ニュースサイトマップ**: `https://yourdomain.com/sitemap-googlenews.xml`
  （設定で有効化した投稿タイプのニュース記事が含まれます）

## 使い方

### 基本的な使い方

1. **投稿タイプの設定**: 各投稿タイプをサイトマップに含めるかを選択
2. **優先度の設定**: 0.0～1.0の範囲で各投稿タイプの優先度を設定
3. **更新頻度の設定**: always, hourly, daily, weekly, monthly, yearly, neverから選択
4. **個別記事の除外**: 投稿編集画面のメタボックスで個別にサイトマップから除外可能

### ニュースサイトマップ

Google News向けに最適化されたサイトマップを生成します。設定で有効化した投稿タイプの記事が含まれます。

### 画像情報の埋め込み

投稿に含まれる画像（アイキャッチ画像、本文中の画像）を自動的に検出し、投稿タイプ別サイトマップ内に `<image:image>` タグとして埋め込みます。画像検索の最適化に役立ちます。

### 動画情報の埋め込み

YouTube・Vimeoの動画コンテンツを自動検出し、投稿タイプ別サイトマップ内に `<video:video>` タグとして埋め込みます。動画検索結果への表示を最適化します。

## 技術仕様

### システム要件

- WordPress: 5.0以上
- PHP: 7.4以上
- メモリ: 最低64MB推奨

### 互換性

- マルチサイト対応
- 主要なSEOプラグインとの併用可能
- カスタム投稿タイプ完全対応

### パフォーマンス

- サイトマップはキャッシュされ、必要時のみ再生成
- 大量の投稿でも高速に動作
- リライトルールを使用した効率的なURL処理

## 更新履歴

最新の変更内容については [CHANGELOG.md](CHANGELOG.md) をご覧ください。

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

GPL-2.0-or-later

## サポート・開発者

**開発者**: 柏崎剛 (Tsuyoshi Kashiwazaki)
**ウェブサイト**: https://www.tsuyoshikashiwazaki.jp/
**サポート**: プラグインに関するご質問や不具合報告は、開発者ウェブサイトまでお問い合わせください。

## 貢献

プルリクエストを歓迎します。大きな変更の場合は、まずissueを開いて変更内容について議論してください。

## サポート

- 不具合報告: [GitHub Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-universal-sitemap/issues)
- 機能リクエスト: [GitHub Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-universal-sitemap/issues)
- 開発者サイト: https://www.tsuyoshikashiwazaki.jp/

---

<div align="center">

**Keywords**: WordPress, SEO, Sitemap, XML Sitemap, Google News, Image Sitemap, Video Sitemap, Search Engine Optimization

Made by [Tsuyoshi Kashiwazaki](https://github.com/TsuyoshiKashiwazaki)

</div>
