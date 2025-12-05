# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.4] - 2025-12-05

### Added
- 動的/静的生成モードの選択機能
- 動的モード：リクエスト時にDBから直接生成、ファイル不要
- 動的モードへの切り替え時に静的ファイルを自動削除
- 投稿0件時に不要なサイトマップファイルを自動削除

### Changed
- 管理画面で動的モード時の表示を最適化（⚡ 動的生成表示、再生成ボタン非表示）

## [1.0.3] - 2025-11-13

### Added
- 自動分割機能：投稿タイプサイトマップ50,000件、ニュースサイトマップ1,000件で自動分割
- GZIP圧縮機能：ファイルサイズを約90-98%削減、管理画面でON/OFF切替可能
- タブ形式の管理画面：サイトマップ&設定、統計情報、使い方の3タブ
- ニュースサイトマップセクションの折りたたみ機能（未使用時は自動折りたたみ）
- HTMLヘッダー出力機能：`<link rel="sitemap">`をON/OFF切替可能
- プラグイン一覧に「設定」リンク
- 詳細な使い方ガイドとトラブルシューティング情報
- Google News特別枠表示（セパレーターと背景色で差別化）

### Changed
- 画像・動画情報をON/OFF切替可能に変更
- Googleニュースサイトマップを投稿タイプ選択式に変更
- UI/UX全般を大幅に改善
- 管理画面の統計表示を別タブに分離
- 使い方ガイドを詳細化

### Fixed
- GZIP ON/OFF切替時に古い形式のファイルを自動削除
- 無効化された投稿タイプのファイル削除処理（.xml.gz対応）
- インデックスサイトマップのファイル存在チェック（GZIP対応）
- インデックスサイトマップのURL件数表示
- 配列未定義アクセスによるエラーを修正
- debug.log設定の重複を解消
- ニュースサイトマップが空の場合のファイル削除処理
