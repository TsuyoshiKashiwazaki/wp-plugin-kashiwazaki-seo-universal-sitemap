<?php
/**
 * Plugin Name: KASHIWAZAKI SEO Universal Sitemap
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: 投稿タイプ別のXMLサイトマップを生成し、ニュース・画像・動画サイトマップにも対応したSEO最適化プラグインです。
 * Version: 1.0.0
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * License: GPL2
 * Text Domain: kashiwazaki-seo-universal-sitemap
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// 定数定義
define('KSUS_VERSION', '1.0.0');
define('KSUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KSUS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KSUS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 必要なクラスファイルを読み込み
require_once KSUS_PLUGIN_DIR . 'includes/class-post-meta.php';
require_once KSUS_PLUGIN_DIR . 'includes/class-sitemap-generator.php';
require_once KSUS_PLUGIN_DIR . 'includes/class-admin.php';

// プラグインの初期化
class KSUS_Main {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // 各クラスの初期化
        add_action('plugins_loaded', array($this, 'init_classes'));

        // プラグイン有効化時
        register_activation_hook(__FILE__, array($this, 'activate'));

        // プラグイン無効化時
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // プラグイン一覧に設定リンクを追加
        add_filter('plugin_action_links_' . KSUS_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    public function init_classes() {
        // 各クラスのインスタンス化
        KSUS_Post_Meta::get_instance();
        KSUS_Sitemap_Generator::get_instance();

        if (is_admin()) {
            KSUS_Admin::get_instance();
        }
    }

    public function activate() {
        // サイトマップディレクトリを作成
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps';
        if (!file_exists($sitemap_dir)) {
            wp_mkdir_p($sitemap_dir);
        }

        // リライトルールを追加してフラッシュ
        KSUS_Sitemap_Generator::get_instance()->add_rewrite_rules();
        flush_rewrite_rules();
    }

    public function deactivate() {
        // リライトルールをフラッシュ
        flush_rewrite_rules();
    }

    /**
     * プラグイン一覧に設定リンクを追加
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=kashiwazaki-seo-universal-sitemap') . '">設定</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// プラグイン起動
KSUS_Main::get_instance();
