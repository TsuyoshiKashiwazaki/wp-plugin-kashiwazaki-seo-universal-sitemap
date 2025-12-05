<?php
/**
 * Plugin Name: Kashiwazaki SEO Universal Sitemap
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: 柏崎剛によるユニバーサルSEOサイトマッププラグイン。投稿タイプ別のサイトマップ、画像・動画サイトマップ、Googleニュースサイトマップに対応。50,000件/1,000件で自動分割、GZIP圧縮対応。
 * Version: 1.0.4
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashiwazaki-seo-universal-sitemap
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数
define('KSUS_VERSION', '1.0.4');
define('KSUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KSUS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * メインクラス
 */
class KSUS_Main {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // クラスファイルの読み込み
        require_once KSUS_PLUGIN_DIR . 'includes/class-sitemap-generator.php';
        require_once KSUS_PLUGIN_DIR . 'includes/class-admin.php';
        require_once KSUS_PLUGIN_DIR . 'includes/class-post-meta.php';

        // 有効化フック
        register_activation_hook(__FILE__, array($this, 'activate'));

        // プラグイン初期化
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // サイトマップ保存用ディレクトリを作成
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps';

        if (!file_exists($sitemap_dir)) {
            wp_mkdir_p($sitemap_dir);
        }

        // リライトルールを追加
        KSUS_Sitemap_Generator::get_instance()->add_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * プラグイン初期化
     */
    public function init() {
        // 各クラスのインスタンスを取得（初期化）
        KSUS_Sitemap_Generator::get_instance();
        KSUS_Admin::get_instance();
        KSUS_Post_Meta::get_instance();
    }
}

// プラグイン起動
KSUS_Main::get_instance();
