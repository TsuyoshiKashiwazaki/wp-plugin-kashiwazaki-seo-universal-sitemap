=== Kashiwazaki SEO Universal Sitemap ===
Contributors: tsuyoshikashiwazaki
Tags: seo, sitemap, xml sitemap, google news, image sitemap, video sitemap
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

投稿タイプ別のXMLサイトマップを生成し、ニュースサイトマップ、画像・動画情報の埋め込みにも対応したSEO最適化プラグインです。

== Description ==

Kashiwazaki SEO Universal Sitemapは、WordPress向けの包括的なサイトマップソリューションです。投稿タイプ別のXMLサイトマップを生成し、Google News向けのニュースサイトマップ、さらに画像検索・動画検索に最適化された情報を含むサイトマップを提供します。

= 主な機能 =

* **投稿タイプ別サイトマップ生成** - 投稿、固定ページ、カスタム投稿タイプに対応
* **ニュースサイトマップ** - Google Newsに最適化されたサイトマップ
* **画像情報の埋め込み** - 画像検索最適化のための画像情報を投稿タイプ別サイトマップに埋め込み
* **動画情報の埋め込み** - YouTube・Vimeoの動画情報を投稿タイプ別サイトマップに埋め込み
* **柔軟な設定** - 各投稿タイプごとに細かく設定可能
* **自動更新** - コンテンツ更新時に自動的にサイトマップを再生成
* **投稿レベルのコントロール** - 個別の投稿でサイトマップへの含有を制御可能

= サイトマップURL =

生成されたサイトマップは以下のURLでアクセスできます：

* インデックスサイトマップ: `https://yourdomain.com/sitemap.xml`
* 投稿タイプ別: `https://yourdomain.com/sitemap-{post_type}.xml`
* ニュースサイトマップ: `https://yourdomain.com/sitemap-googlenews.xml`

= システム要件 =

* WordPress: 5.0以上
* PHP: 7.4以上
* メモリ: 最低64MB推奨

== Installation ==

1. プラグインファイルを `/wp-content/plugins/kashiwazaki-seo-universal-sitemap/` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 「設定」→「Kashiwazaki SEO Universal Sitemap」から設定を行う
4. サイトマップに含める投稿タイプを選択し、設定を保存

== Frequently Asked Questions ==

= サイトマップはどこに保存されますか？ =

サイトマップは `/wp-content/uploads/sitemaps/` ディレクトリに保存されます。

= サイトマップは自動的に更新されますか？ =

はい、投稿の公開・更新時に自動的にサイトマップが再生成されます。

= 特定の投稿をサイトマップから除外できますか？ =

はい、投稿編集画面のメタボックスで個別にサイトマップから除外できます。

= Google Search Consoleに登録する必要がありますか？ =

はい、生成されたサイトマップURLをGoogle Search Consoleに登録することを推奨します。

== Screenshots ==

1. 管理画面 - サイトマップ設定
2. 投稿タイプ別設定画面
3. ニュースサイトマップ設定
4. 投稿編集画面のメタボックス

== Changelog ==

= 1.0.3 =
* Added: 投稿タイプサイトマップの自動分割機能（50,000件ごと）
* Added: ニュースサイトマップの自動分割機能（1,000件ごと）
* Added: GZIP圧縮機能（ファイルサイズ約90-98%削減）
* Added: タブ形式の管理画面（サイトマップ&設定、統計情報、使い方）
* Added: ニュースサイトマップセクションの折りたたみ機能
* Added: HTMLヘッダー出力機能（<link rel="sitemap">）ON/OFF切替可能
* Added: プラグイン一覧に「設定」リンク
* Added: 詳細な使い方ガイドとトラブルシューティング情報
* Added: Google News特別枠表示
* Changed: 画像・動画情報をON/OFF切替可能に変更
* Changed: Googleニュースサイトマップを投稿タイプ選択式に変更
* Changed: 管理画面UI/UXを大幅改善
* Fixed: GZIP ON/OFF切替時に古い形式のファイルを自動削除
* Fixed: 無効化された投稿タイプのファイル削除処理（.xml.gz対応）
* Fixed: インデックスサイトマップのファイル存在チェック（GZIP対応）
* Fixed: debug.log設定の重複を解消

= 1.0.2 =
* Fixed: カスタム投稿タイプのサイトマップがインデックスに含まれない問題を修正
* Fixed: サイトマップ生成順序を変更（投稿タイプ別・ニュース → インデックス）
* Improved: すべてのカスタム投稿タイプが正しくインデックスサイトマップに反映されるように改善

= 1.0.1 =
* Improved: 画像情報のメタデータ取得ロジックを改善
* Improved: alt/title/caption の優先順位を最適化
* Improved: HTMLタグ除去とトリミング処理を強化
* Fixed: 空文字列チェックをより厳密に変更

= 1.0.0 =
* Initial release
* 投稿タイプ別XMLサイトマップ生成機能
* ニュースサイトマップ（Google News対応）
* 画像情報の埋め込み（投稿タイプ別サイトマップ内に画像検索最適化情報を追加）
* 動画情報の埋め込み（投稿タイプ別サイトマップ内にYouTube・Vimeo動画情報を追加）
* 投稿タイプごとの優先度と更新頻度設定
* 個別投稿でのサイトマップ含有制御機能

== Upgrade Notice ==

= 1.0.2 =
カスタム投稿タイプのサイトマップが正しくインデックスに反映されるようになりました。カスタム投稿タイプを使用している場合は必ずアップデートしてください。

= 1.0.1 =
画像情報のメタデータ処理が改善されました。より正確な画像情報が検索エンジンに提供されます。

= 1.0.0 =
初回リリースです。
