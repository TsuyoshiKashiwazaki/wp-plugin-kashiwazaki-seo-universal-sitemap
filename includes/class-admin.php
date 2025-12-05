<?php
/**
 * 管理画面クラス
 *
 * @package Kashiwazaki SEO Universal Sitemap
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
        add_action('update_option_ksus_enable_gzip', array($this, 'regenerate_on_settings_update'), 10, 2);
        add_filter('pre_update_option_ksus_generation_mode', array($this, 'on_generation_mode_change'), 10, 2);
        add_filter('plugin_action_links_kashiwazaki-seo-universal-sitemap/kashiwazaki-seo-universal-sitemap.php', array($this, 'add_plugin_action_links'));
    }

    /**
     * プラグイン一覧に「設定」リンクを追加
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=kashiwazaki-seo-universal-sitemap') . '">設定</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * 管理画面メニューを追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'Kashiwazaki SEO Universal Sitemap',
            'Kashiwazaki SEO Universal Sitemap',
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
        // 動的モードの場合は静的ファイルを生成しない
        if (get_option('ksus_generation_mode', 'static') === 'dynamic') {
            return;
        }

        $upload_dir = wp_upload_dir();
        $sitemap_file = $upload_dir['basedir'] . '/sitemaps/sitemap.xml';
        $sitemap_file_gz = $upload_dir['basedir'] . '/sitemaps/sitemap.xml.gz';

        if (!file_exists($sitemap_file) && !file_exists($sitemap_file_gz)) {
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
     * サイトマップURLを取得（分割ファイル対応）
     */
    public static function get_sitemap_urls() {
        $home_url = home_url('/');
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        // インデックスサイトマップのURL（GZIPチェック）
        $index_url = $home_url . 'sitemap.xml';
        if (file_exists($sitemap_dir . 'sitemap.xml.gz')) {
            $index_url = $home_url . 'sitemap.xml.gz';
        }

        $urls = array(
            'index' => array(
                'url' => $index_url,
                'files' => array($index_url)
            )
        );

        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment']);

        foreach ($post_types as $post_type) {
            $file_urls = array();

            // まず番号なしファイル（常に最初）を確認（.xml.gz と .xml 両方）
            $gz_file = $sitemap_dir . 'sitemap-' . $post_type . '.xml.gz';
            $xml_file = $sitemap_dir . 'sitemap-' . $post_type . '.xml';

            if (file_exists($gz_file)) {
                $file_urls[] = $home_url . 'sitemap-' . $post_type . '.xml.gz';
            } elseif (file_exists($xml_file)) {
                $file_urls[] = $home_url . 'sitemap-' . $post_type . '.xml';
            }

            // 次に分割ファイル（-2以降）を検索（数字のみ、.xml と .xml.gz 両方）
            $numbered_files = array();
            foreach (array('xml', 'xml.gz') as $ext) {
                $pattern = $sitemap_dir . 'sitemap-' . $post_type . '-*.' . $ext;
                $files = glob($pattern);

                if ($files && !empty($files)) {
                    foreach ($files as $file) {
                        $filename = basename($file);
                        // sitemap-{post_type}-{数字}.xml(.gz) の形式のみマッチ
                        if (preg_match('/^sitemap-' . preg_quote($post_type, '/') . '-(\d+)\.(xml|xml\.gz)$/', $filename)) {
                            $numbered_files[] = $file;
                        }
                    }
                }
            }

            if (!empty($numbered_files)) {
                // ファイル名でソート（数値順）
                usort($numbered_files, function($a, $b) {
                    preg_match('/-(\d+)\.(xml|xml\.gz)$/', $a, $match_a);
                    preg_match('/-(\d+)\.(xml|xml\.gz)$/', $b, $match_b);
                    $num_a = isset($match_a[1]) ? intval($match_a[1]) : 0;
                    $num_b = isset($match_b[1]) ? intval($match_b[1]) : 0;
                    return $num_a - $num_b;
                });

                foreach ($numbered_files as $file) {
                    $filename = basename($file);
                    $file_urls[] = $home_url . $filename;
                }
            }

            if (!empty($file_urls)) {
                $urls[$post_type] = array(
                    'url' => $file_urls[0], // 最初のファイルをメインURLとする
                    'files' => $file_urls
                );
            }
        }

        // ニュースサイトマップ（分割ファイル対応）
        $news_file_urls = array();

        // まず番号なしファイル（常に最初）を確認（.xml.gz と .xml 両方）
        $gz_file = $sitemap_dir . 'sitemap-googlenews.xml.gz';
        $xml_file = $sitemap_dir . 'sitemap-googlenews.xml';

        if (file_exists($gz_file)) {
            $news_file_urls[] = $home_url . 'sitemap-googlenews.xml.gz';
        } elseif (file_exists($xml_file)) {
            $news_file_urls[] = $home_url . 'sitemap-googlenews.xml';
        }

        // 次に分割ファイル（-2以降）を検索（.xml と .xml.gz 両方）
        $numbered_files = array();
        foreach (array('xml', 'xml.gz') as $ext) {
            $pattern = $sitemap_dir . 'sitemap-googlenews-*.' . $ext;
            $files = glob($pattern);

            if ($files && !empty($files)) {
                foreach ($files as $file) {
                    $filename = basename($file);
                    // sitemap-googlenews-{数字}.xml(.gz) の形式のみマッチ
                    if (preg_match('/^sitemap-googlenews-(\d+)\.(xml|xml\.gz)$/', $filename)) {
                        $numbered_files[] = $file;
                    }
                }
            }
        }

        if (!empty($numbered_files)) {
            // ファイル名でソート（数値順）
            usort($numbered_files, function($a, $b) {
                preg_match('/-(\d+)\.(xml|xml\.gz)$/', $a, $match_a);
                preg_match('/-(\d+)\.(xml|xml\.gz)$/', $b, $match_b);
                $num_a = isset($match_a[1]) ? intval($match_a[1]) : 0;
                $num_b = isset($match_b[1]) ? intval($match_b[1]) : 0;
                return $num_a - $num_b;
            });

            foreach ($numbered_files as $file) {
                $filename = basename($file);
                $news_file_urls[] = $home_url . $filename;
            }
        }

        if (!empty($news_file_urls)) {
            $urls['googlenews'] = array(
                'url' => $news_file_urls[0],
                'files' => $news_file_urls
            );
        }

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

        // GZIPファイルの場合は解凍して読み込む
        try {
            if (substr($filename, -3) === '.gz') {
                $content = @file_get_contents('compress.zlib://' . $file_path);
            } else {
                $content = @file_get_contents($file_path);
            }

            if ($content === false || empty($content)) {
                return 0;
            }
        } catch (Exception $e) {
            return 0;
        }

        // インデックスサイトマップの場合は<sitemap>タグを数える
        if (strpos($filename, 'sitemap.xml') === 0) {
            $count = substr_count($content, '<sitemap>');
        } else {
            // 通常のサイトマップは<url>タグを数える
            $count = substr_count($content, '<url>');
        }

        return $count;
    }

    /**
     * 投稿タイプまたはニュースサイトマップの統計情報を取得（分割ファイル対応）
     */
    public static function get_split_sitemap_stats($type) {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        $stats = array(
            'file_count' => 0,
            'total_urls' => 0,
            'files' => array()
        );

        $all_files = array();

        // まず番号なしファイルを確認（.xml.gz と .xml 両方）
        if ($type === 'googlenews') {
            $gz_file = $sitemap_dir . 'sitemap-googlenews.xml.gz';
            $xml_file = $sitemap_dir . 'sitemap-googlenews.xml';
        } else {
            $gz_file = $sitemap_dir . 'sitemap-' . $type . '.xml.gz';
            $xml_file = $sitemap_dir . 'sitemap-' . $type . '.xml';
        }

        if (file_exists($gz_file)) {
            $all_files[] = $gz_file;
        } elseif (file_exists($xml_file)) {
            $all_files[] = $xml_file;
        }

        // 次に分割ファイル（-2以降）を検索（数字のみ、.xml と .xml.gz 両方）
        $numbered_files = array();
        foreach (array('xml', 'xml.gz') as $ext) {
            if ($type === 'googlenews') {
                $pattern = $sitemap_dir . 'sitemap-googlenews-*.' . $ext;
            } else {
                $pattern = $sitemap_dir . 'sitemap-' . $type . '-*.' . $ext;
            }

            $files = glob($pattern);

            if ($files && !empty($files)) {
                foreach ($files as $file) {
                    $filename = basename($file);
                    // sitemap-{type}-{数字}.xml(.gz) の形式のみマッチ
                    if ($type === 'googlenews') {
                        if (preg_match('/^sitemap-googlenews-(\d+)\.(xml|xml\.gz)$/', $filename)) {
                            $numbered_files[] = $file;
                        }
                    } else {
                        if (preg_match('/^sitemap-' . preg_quote($type, '/') . '-(\d+)\.(xml|xml\.gz)$/', $filename)) {
                            $numbered_files[] = $file;
                        }
                    }
                }
            }
        }

        if (!empty($numbered_files)) {
            // ファイル名でソート（数値順）
            usort($numbered_files, function($a, $b) {
                preg_match('/-(\d+)\.(xml|xml\.gz)$/', $a, $match_a);
                preg_match('/-(\d+)\.(xml|xml\.gz)$/', $b, $match_b);
                $num_a = isset($match_a[1]) ? intval($match_a[1]) : 0;
                $num_b = isset($match_b[1]) ? intval($match_b[1]) : 0;
                return $num_a - $num_b;
            });

            $all_files = array_merge($all_files, $numbered_files);
        }

        if (empty($all_files)) {
            return $stats;
        }

        $stats['file_count'] = count($all_files);

        foreach ($all_files as $file) {
            $filename = basename($file);
            $url_count = self::get_sitemap_url_count($filename);
            $stats['total_urls'] += $url_count;
            $stats['files'][] = array(
                'filename' => $filename,
                'url_count' => $url_count
            );
        }

        return $stats;
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

        register_setting('ksus_settings', 'ksus_enable_gzip', array(
            'type' => 'boolean',
            'default' => false
        ));

        register_setting('ksus_settings', 'ksus_enable_head_link', array(
            'type' => 'boolean',
            'default' => true
        ));

        register_setting('ksus_settings', 'ksus_generation_mode', array(
            'type' => 'string',
            'default' => 'static',
            'sanitize_callback' => array($this, 'sanitize_generation_mode')
        ));
    }

    /**
     * 生成モード設定のサニタイズ
     */
    public function sanitize_generation_mode($input) {
        $valid_modes = array('static', 'dynamic');
        return in_array($input, $valid_modes) ? $input : 'static';
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
            // 動的モードの場合は静的ファイルを生成しない
            if (get_option('ksus_generation_mode', 'static') === 'dynamic') {
                return;
            }

            KSUS_Sitemap_Generator::get_instance()->generate_all_sitemaps();

            // 成功メッセージを追加（管理画面のみ）
            if (function_exists('add_settings_error')) {
                add_settings_error(
                    'ksus_messages',
                    'ksus_message',
                    'サイトマップを再生成しました。',
                    'updated'
                );
            }
        }
    }

    /**
     * 生成モード変更時の処理（pre_update_option フィルター）
     */
    public function on_generation_mode_change($new_value, $old_value) {
        if ($old_value === $new_value) {
            return $new_value;
        }

        if ($new_value === 'dynamic') {
            // 動的モードに切り替え → 静的ファイルを全削除
            $this->delete_all_sitemap_files();

            if (function_exists('add_settings_error')) {
                add_settings_error(
                    'ksus_messages',
                    'ksus_message',
                    '動的生成モードに切り替えました。静的ファイルを削除しました。',
                    'updated'
                );
            }
        } else {
            // 静的モードに切り替え → サイトマップを生成
            KSUS_Sitemap_Generator::get_instance()->generate_all_sitemaps();

            if (function_exists('add_settings_error')) {
                add_settings_error(
                    'ksus_messages',
                    'ksus_message',
                    '静的生成モードに切り替えました。サイトマップを生成しました。',
                    'updated'
                );
            }
        }

        return $new_value;
    }

    /**
     * 全てのサイトマップファイルを削除
     */
    private function delete_all_sitemap_files() {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        if (!is_dir($sitemap_dir)) {
            return;
        }

        // sitemapsディレクトリ内の全ファイルを削除
        $files = glob($sitemap_dir . '*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        // ディレクトリ自体も削除
        @rmdir($sitemap_dir);
    }
}
