<?php
/**
 * 管理画面クラス
 *
 * @package KASHIWAZAKI SEO Universal Sitemap
 */

if (!defined('ABSPATH')) {
    exit;
}

class KSUS_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_ksus_regenerate_sitemaps', array($this, 'ajax_regenerate_sitemaps'));
        add_action('admin_init', array($this, 'maybe_generate_initial_sitemaps'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('update_option_ksus_enabled_post_types', array($this, 'regenerate_on_settings_update'), 10, 2);
        add_action('update_option_ksus_news_post_types', array($this, 'regenerate_on_settings_update'), 10, 2);
        add_action('update_option_ksus_include_images', array($this, 'regenerate_on_settings_update'), 10, 2);
        add_action('update_option_ksus_include_videos', array($this, 'regenerate_on_settings_update'), 10, 2);
        add_action('update_option_ksus_post_type_settings', array($this, 'regenerate_on_settings_update'), 10, 2);
    }

    /**
     * 管理画面メニューを追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'KASHIWAZAKI SEO Universal Sitemap',
            'KASHIWAZAKI SEO Universal Sitemap',
            'manage_options',
            'kashiwazaki-seo-universal-sitemap',
            array($this, 'render_admin_page'),
            'dashicons-media-code',
            81
        );
    }

    /**
     * 管理画面用アセットを読み込み
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_kashiwazaki-seo-universal-sitemap') {
            return;
        }

        $css_file = KSUS_PLUGIN_DIR . 'assets/css/admin.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'ksus-admin-css',
                KSUS_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                KSUS_VERSION
            );
        }

        $js_file = KSUS_PLUGIN_DIR . 'assets/js/admin.js';
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'ksus-admin-js',
                KSUS_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                KSUS_VERSION,
                true
            );

            wp_localize_script('ksus-admin-js', 'ksusAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ksus_admin_nonce')
            ));
        }
    }

    /**
     * 管理画面のHTMLを出力
     */
    public function render_admin_page() {
        include KSUS_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * AJAX: サイトマップ再生成
     */
    public function ajax_regenerate_sitemaps() {
        check_ajax_referer('ksus_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '権限がありません。'));
        }

        KSUS_Sitemap_Generator::get_instance()->generate_all_sitemaps();

        wp_send_json_success(array('message' => 'サイトマップを再生成しました。'));
    }

    /**
     * 初回サイトマップ生成
     */
    public function maybe_generate_initial_sitemaps() {
        $upload_dir = wp_upload_dir();
        $sitemap_file = $upload_dir['basedir'] . '/sitemaps/sitemap.xml';

        if (!file_exists($sitemap_file)) {
            KSUS_Sitemap_Generator::get_instance()->generate_all_sitemaps();
        }
    }

    /**
     * サイトマップ統計を取得
     */
    public static function get_sitemap_stats() {
        $stats = array();

        // 投稿タイプ別の統計
        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment']);

        foreach ($post_types as $post_type) {
            $count = wp_count_posts($post_type);
            $stats[$post_type] = array(
                'label' => get_post_type_object($post_type)->labels->name,
                'total' => $count->publish ?? 0,
                'standard' => 0,
                'image' => 0,
                'video' => 0,
                'exclude' => 0
            );

            // メタデータごとのカウント
            $meta_counts = self::count_posts_by_sitemap_type($post_type);
            $stats[$post_type] = array_merge($stats[$post_type], $meta_counts);
        }

        return $stats;
    }

    /**
     * サイトマップタイプ別の投稿数をカウント
     */
    private static function count_posts_by_sitemap_type($post_type) {
        global $wpdb;

        $counts = array(
            'standard' => 0,
            'image' => 0,
            'video' => 0,
            'exclude' => 0
        );

        // excludeのカウント
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm.meta_key = '_ksus_sitemap_type'
            AND pm.meta_value = 'exclude'",
            $post_type
        ));
        $counts['exclude'] = (int) $count;

        // standard (excludeでないすべて)
        $total = wp_count_posts($post_type);
        $counts['standard'] = ($total->publish ?? 0) - $counts['exclude'];

        // 画像を含む投稿数（除外以外）
        $image_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ksus_sitemap_type'
            LEFT JOIN {$wpdb->postmeta} thumb ON p.ID = thumb.post_id AND thumb.meta_key = '_thumbnail_id'
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'exclude')
            AND (thumb.meta_value IS NOT NULL OR p.post_content LIKE '%%<img%%')",
            $post_type
        ));
        $counts['image'] = (int) $image_count;

        // 動画を含む投稿数（除外以外）
        $video_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ksus_sitemap_type'
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'exclude')
            AND (p.post_content LIKE '%%youtube.com%%' OR p.post_content LIKE '%%youtu.be%%' OR p.post_content LIKE '%%vimeo.com%%')",
            $post_type
        ));
        $counts['video'] = (int) $video_count;

        return $counts;
    }

    /**
     * サイトマップURLを取得
     */
    public static function get_sitemap_urls() {
        $home_url = home_url('/');
        $urls = array(
            'index' => $home_url . 'sitemap.xml'
        );

        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment']);

        foreach ($post_types as $post_type) {
            $urls[$post_type] = $home_url . 'sitemap-' . $post_type . '.xml';
        }

        $urls['googlenews'] = $home_url . 'sitemap-googlenews.xml';
        $urls['image'] = $home_url . 'sitemap-image.xml';
        $urls['video'] = $home_url . 'sitemap-video.xml';

        return $urls;
    }

    /**
     * サイトマップのURL件数を取得
     */
    public static function get_sitemap_url_count($filename) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/sitemaps/' . $filename;

        if (!file_exists($file_path)) {
            return 0;
        }

        $content = file_get_contents($file_path);

        // インデックスサイトマップの場合は<sitemap>タグを数える
        if ($filename === 'sitemap.xml') {
            $count = substr_count($content, '<sitemap>');
        } else {
            // 通常のサイトマップは<url>タグを数える
            $count = substr_count($content, '<url>');
        }

        return $count;
    }

    /**
     * 設定を登録
     */
    public function register_settings() {
        // すべて同じ設定グループに統一
        register_setting('ksus_settings', 'ksus_news_post_types', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_news_post_types')
        ));

        register_setting('ksus_settings', 'ksus_include_images', array(
            'type' => 'boolean',
            'default' => true
        ));

        register_setting('ksus_settings', 'ksus_include_videos', array(
            'type' => 'boolean',
            'default' => true
        ));

        register_setting('ksus_settings', 'ksus_enabled_post_types', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_enabled_post_types')
        ));

        register_setting('ksus_settings', 'ksus_post_type_settings', array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => array($this, 'sanitize_post_type_settings')
        ));
    }

    /**
     * ニュースサイトマップ設定のサニタイズ
     */
    public function sanitize_news_post_types($input) {
        if (!is_array($input)) {
            return array();
        }

        $valid_post_types = get_post_types(array('public' => true), 'names');
        unset($valid_post_types['attachment']);

        $sanitized = array();
        foreach ($input as $post_type) {
            // 空文字列をスキップ
            if (empty($post_type)) {
                continue;
            }
            if (in_array($post_type, $valid_post_types)) {
                $sanitized[] = sanitize_key($post_type);
            }
        }

        return $sanitized;
    }

    /**
     * 有効な投稿タイプ設定のサニタイズ
     */
    public function sanitize_enabled_post_types($input) {
        if (!is_array($input)) {
            return array();
        }

        $valid_post_types = get_post_types(array('public' => true), 'names');
        unset($valid_post_types['attachment']);

        $sanitized = array();
        foreach ($input as $post_type) {
            // 空文字列をスキップ
            if (empty($post_type)) {
                continue;
            }
            if (in_array($post_type, $valid_post_types)) {
                $sanitized[] = sanitize_key($post_type);
            }
        }

        return $sanitized;
    }

    /**
     * 投稿タイプ設定のサニタイズ
     */
    public function sanitize_post_type_settings($input) {
        if (!is_array($input)) {
            return array();
        }

        $valid_post_types = get_post_types(array('public' => true), 'names');
        unset($valid_post_types['attachment']);

        $valid_changefreq = array('always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never');

        $sanitized = array();
        foreach ($input as $post_type => $settings) {
            if (!in_array($post_type, $valid_post_types)) {
                continue;
            }

            $sanitized[$post_type] = array(
                'changefreq' => isset($settings['changefreq']) && in_array($settings['changefreq'], $valid_changefreq)
                    ? $settings['changefreq']
                    : 'weekly',
                'priority' => isset($settings['priority'])
                    ? max(0.0, min(1.0, floatval($settings['priority'])))
                    : 0.5
            );
        }

        return $sanitized;
    }

    /**
     * 設定更新時にサイトマップを再生成
     */
    public function regenerate_on_settings_update($old_value, $new_value) {
        // 値が変わった場合のみ再生成
        if ($old_value !== $new_value) {
            KSUS_Sitemap_Generator::get_instance()->generate_all_sitemaps();

            // 成功メッセージを追加
            add_settings_error(
                'ksus_messages',
                'ksus_message',
                'サイトマップを再生成しました。',
                'updated'
            );
        }
    }
}
