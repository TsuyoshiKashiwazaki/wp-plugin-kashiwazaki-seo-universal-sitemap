# 🚀 KASHIWAZAKI SEO Universal Sitemap

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-universal-sitemap/releases)

投稿タイプ別のXMLサイトマップを生成し、ニュース・画像・動画サイトマップにも対応したSEO最適化プラグインです。

> 🎯 **検索エンジン最適化のための包括的なサイトマップソリューション**

## 主な機能

- ✅ **投稿タイプ別サイトマップ生成** - 投稿、固定ページ、カスタム投稿タイプに対応
- 📰 **ニュースサイトマップ** - Google Newsに最適化されたサイトマップ
- 🖼️ **画像サイトマップ** - 画像検索最適化のための画像情報を含むサイトマップ
- 🎬 **動画サイトマップ** - 動画コンテンツを検索エンジンに最適化
- ⚙️ **柔軟な設定** - 各投稿タイプごとに細かく設定可能
- 🔄 **自動更新** - コンテンツ更新時に自動的にサイトマップを再生成
- 📊 **投稿レベルのコントロール** - 個別の投稿でサイトマップへの含有を制御可能

## 🚀 クイックスタート

### インストール

1. プラグインファイルを `/wp-content/plugins/kashiwazaki-seo-universal-sitemap/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 「設定」→「KASHIWAZAKI SEO Universal Sitemap」から設定を行う

### 基本設定

1. 管理画面でサイトマップに含める投稿タイプを選択
2. 各投稿タイプの優先度と更新頻度を設定
3. 必要に応じてニュース・画像・動画サイトマップを有効化
4. 設定を保存してサイトマップを生成

### サイトマップURL

生成されたサイトマップは以下のURLでアクセスできます：

- インデックスサイトマップ: `https://yourdomain.com/sitemap.xml`
- 投稿タイプ別: `https://yourdomain.com/sitemap-{post_type}.xml`
- ニュースサイトマップ: `https://yourdomain.com/sitemap-news.xml`
- 画像サイトマップ: `https://yourdomain.com/sitemap-image.xml`
- 動画サイトマップ: `https://yourdomain.com/sitemap-video.xml`

## 使い方

### 基本的な使い方

1. **投稿タイプの設定**: 各投稿タイプをサイトマップに含めるかを選択
2. **優先度の設定**: 0.0～1.0の範囲で各投稿タイプの優先度を設定
3. **更新頻度の設定**: always, hourly, daily, weekly, monthly, yearly, neverから選択
4. **個別記事の除外**: 投稿編集画面のメタボックスで個別にサイトマップから除外可能

### ニュースサイトマップ

Google News向けに最適化されたサイトマップを生成します。過去48時間以内の記事が自動的に含まれます。

### 画像サイトマップ

投稿に含まれる画像を自動的に検出し、画像検索最適化のためのサイトマップを生成します。

### 動画サイトマップ

動画コンテンツの情報を含むサイトマップを生成し、動画検索結果への表示を最適化します。

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

### v1.0.0 (2025-10-08)

- 初回リリース
- 投稿タイプ別サイトマップ生成機能
- ニュース・画像・動画サイトマップ対応
- 個別投稿レベルでの制御機能

## ライセンス

GPL-2.0-or-later

## サポート・開発者

**開発者**: 柏崎剛 (Tsuyoshi Kashiwazaki)
**ウェブサイト**: https://www.tsuyoshikashiwazaki.jp/
**サポート**: プラグインに関するご質問や不具合報告は、開発者ウェブサイトまでお問い合わせください。

## 🤝 貢献

プルリクエストを歓迎します。大きな変更の場合は、まずissueを開いて変更内容について議論してください。

## 📞 サポート

- 不具合報告: [GitHub Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-universal-sitemap/issues)
- 機能リクエスト: [GitHub Issues](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-universal-sitemap/issues)
- 開発者サイト: https://www.tsuyoshikashiwazaki.jp/

---

<div align="center">

**🔍 Keywords**: WordPress, SEO, Sitemap, XML Sitemap, Google News, Image Sitemap, Video Sitemap, Search Engine Optimization

Made with ❤️ by [Tsuyoshi Kashiwazaki](https://github.com/TsuyoshiKashiwazaki)

</div>
