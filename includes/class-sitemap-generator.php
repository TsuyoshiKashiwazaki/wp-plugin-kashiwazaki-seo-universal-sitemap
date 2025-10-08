<?php
/**
 * サイトマップ生成クラス
 *
 * @package KASHIWAZAKI SEO Universal Sitemap
 */

if (!defined('ABSPATH')) {
    exit;
}

class KSUS_Sitemap_Generator {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('ksus_regenerate_sitemaps', array($this, 'generate_all_sitemaps'));
        add_action('save_post', array($this, 'maybe_regenerate_sitemaps'), 20);
        add_action('delete_post', array($this, 'maybe_regenerate_sitemaps'), 20);
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'serve_sitemap'));
        add_filter('redirect_canonical', array($this, 'disable_sitemap_redirect'), 10, 2);
    }

    /**
     * すべてのサイトマップを生成
     */
    public function generate_all_sitemaps() {
        $this->generate_index_sitemap();
        $this->generate_post_type_sitemaps();
        $this->generate_news_sitemap();
    }

    /**
     * インデックスサイトマップを生成
     */
    private function generate_index_sitemap() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $home_url = home_url('/');
        $lastmod = date('c', current_time('timestamp'));
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        // 投稿タイプ別サイトマップ
        $post_types = $this->get_allowed_post_types();
        $enabled_post_types = get_option('ksus_enabled_post_types', false);

        // 初回のみ全て有効
        if ($enabled_post_types === false) {
            $enabled_post_types = $post_types;
        }

        foreach ($post_types as $post_type) {
            // 有効で、かつファイルが存在する場合のみインデックスに含める
            if (in_array($post_type, $enabled_post_types) && file_exists($sitemap_dir . 'sitemap-' . $post_type . '.xml')) {
                $xml .= "\t<sitemap>\n";
                $xml .= "\t\t<loc>" . esc_url($home_url . 'sitemap-' . $post_type . '.xml') . "</loc>\n";
                $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
                $xml .= "\t</sitemap>\n";
            }
        }

        // ニュースサイトマップ（ファイルが存在する場合のみ）
        if ($this->has_news_posts() && file_exists($sitemap_dir . 'sitemap-googlenews.xml')) {
            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>" . esc_url($home_url . 'sitemap-googlenews.xml') . "</loc>\n";
            $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
            $xml .= "\t</sitemap>\n";
        }

        $xml .= '</sitemapindex>';

        // ファイルに保存
        $this->save_sitemap('sitemap.xml', $xml);
    }

    /**
     * 投稿タイプ別サイトマップを生成
     */
    private function generate_post_type_sitemaps() {
        $post_types = $this->get_allowed_post_types();
        $enabled_post_types = get_option('ksus_enabled_post_types', false);

        // 初回のみ全て有効
        if ($enabled_post_types === false) {
            $enabled_post_types = $post_types;
        }

        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        foreach ($post_types as $post_type) {
            // 有効な投稿タイプのみ生成
            if (in_array($post_type, $enabled_post_types)) {
                $this->generate_post_type_sitemap($post_type);
            } else {
                // 無効な投稿タイプのサイトマップファイルを削除
                $file_path = $sitemap_dir . 'sitemap-' . $post_type . '.xml';
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
    }

    /**
     * 特定の投稿タイプのサイトマップを生成
     */
    private function generate_post_type_sitemap($post_type) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1
        );

        $posts = get_posts($args);
        $url_count = 0;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
        $xml .= 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        foreach ($posts as $post) {
            $sitemap_type = get_post_meta($post->ID, '_ksus_sitemap_type', true);

            // 除外は含めない
            if ($sitemap_type === 'exclude') {
                continue;
            }

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url(get_permalink($post->ID)) . "</loc>\n";
            $xml .= "\t\t<lastmod>" . get_post_modified_time('c', false, $post) . "</lastmod>\n";
            $xml .= "\t\t<changefreq>" . $this->get_change_frequency($post_type) . "</changefreq>\n";
            $xml .= "\t\t<priority>" . $this->get_priority($post_type) . "</priority>\n";

            // 設定に基づいて画像・動画情報を含める
            $include_images = get_option('ksus_include_images', true);
            $include_videos = get_option('ksus_include_videos', true);

            if ($include_images) {
                $images_xml = $this->get_images_xml($post);
                if (!empty($images_xml)) {
                    $xml .= $images_xml;
                }
            }

            if ($include_videos) {
                $videos_xml = $this->get_videos_xml($post);
                if (!empty($videos_xml)) {
                    $xml .= $videos_xml;
                }
            }

            $xml .= "\t</url>\n";
            $url_count++;
        }

        $xml .= '</urlset>';

        // URLが1件以上ある場合のみファイルに保存
        if ($url_count > 0) {
            $this->save_sitemap('sitemap-' . $post_type . '.xml', $xml);
        }
    }

    /**
     * ニュースサイトマップを生成
     */
    private function generate_news_sitemap() {
        // 設定から対象投稿タイプを取得
        $news_post_types = get_option('ksus_news_post_types', array());

        if (empty($news_post_types)) {
            // 設定がない場合は既存ファイルを削除
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/sitemaps/sitemap-googlenews.xml';
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            return;
        }

        $args = array(
            'post_type' => $news_post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1
        );

        $posts = get_posts($args);
        $url_count = 0;

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" ';
        $xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
        $xml .= 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        foreach ($posts as $post) {
            $sitemap_type = get_post_meta($post->ID, '_ksus_sitemap_type', true);

            // 除外は含めない
            if ($sitemap_type === 'exclude') {
                continue;
            }

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url(get_permalink($post->ID)) . "</loc>\n";
            $xml .= "\t\t<news:news>\n";
            $xml .= "\t\t\t<news:publication>\n";
            $xml .= "\t\t\t\t<news:name>" . htmlspecialchars(get_bloginfo('name'), ENT_XML1, 'UTF-8') . "</news:name>\n";
            $xml .= "\t\t\t\t<news:language>" . htmlspecialchars(get_bloginfo('language'), ENT_XML1, 'UTF-8') . "</news:language>\n";
            $xml .= "\t\t\t</news:publication>\n";
            $xml .= "\t\t\t<news:publication_date>" . get_post_time('c', false, $post) . "</news:publication_date>\n";
            $xml .= "\t\t\t<news:title>" . htmlspecialchars($post->post_title, ENT_XML1, 'UTF-8') . "</news:title>\n";
            $xml .= "\t\t</news:news>\n";

            // 設定に基づいて画像・動画情報を含める
            $include_images = get_option('ksus_include_images', true);
            $include_videos = get_option('ksus_include_videos', true);

            if ($include_images) {
                $images_xml = $this->get_images_xml($post);
                if (!empty($images_xml)) {
                    $xml .= $images_xml;
                }
            }

            if ($include_videos) {
                $videos_xml = $this->get_videos_xml($post);
                if (!empty($videos_xml)) {
                    $xml .= $videos_xml;
                }
            }

            $xml .= "\t</url>\n";
            $url_count++;
        }

        $xml .= '</urlset>';

        // URLが1件以上ある場合のみファイルに保存、0件の場合は既存ファイルを削除
        if ($url_count > 0) {
            $this->save_sitemap('sitemap-googlenews.xml', $xml);
        } else {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/sitemaps/sitemap-googlenews.xml';
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }

    /**
     * 画像情報のXMLを取得
     */
    private function get_images_xml($post) {
        $xml = '';
        $images = array();
        $seen_urls = array();

        // アイキャッチ画像
        if (has_post_thumbnail($post->ID)) {
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
            if ($image_url) {
                $images[] = array(
                    'loc' => $image_url,
                    'title' => get_the_title($thumbnail_id),
                    'caption' => wp_get_attachment_caption($thumbnail_id)
                );
                $seen_urls[$image_url] = true;
            }
        }

        // 本文中の画像
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $img_url) {
                // 重複チェック
                if (!isset($seen_urls[$img_url])) {
                    $images[] = array(
                        'loc' => $img_url,
                        'title' => '',
                        'caption' => ''
                    );
                    $seen_urls[$img_url] = true;
                }
            }
        }

        // 画像を追加
        foreach ($images as $image) {
            $xml .= "\t\t<image:image>\n";
            $xml .= "\t\t\t<image:loc>" . esc_url($image['loc']) . "</image:loc>\n";
            if (!empty($image['title'])) {
                $title = htmlspecialchars(html_entity_decode($image['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_XML1, 'UTF-8');
                $xml .= "\t\t\t<image:title>" . $title . "</image:title>\n";
            }
            if (!empty($image['caption'])) {
                $caption = htmlspecialchars(html_entity_decode($image['caption'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_XML1, 'UTF-8');
                $xml .= "\t\t\t<image:caption>" . $caption . "</image:caption>\n";
            }
            $xml .= "\t\t</image:image>\n";
        }

        return $xml;
    }

    /**
     * 動画情報のXMLを取得
     */
    private function get_videos_xml($post) {
        $xml = '';
        $seen_videos = array();

        // 本文中のYouTube動画を検出（URL形式）
        preg_match_all('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/i', $post->post_content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $video_id) {
                // 重複チェック
                if (isset($seen_videos['youtube_' . $video_id])) {
                    continue;
                }
                $seen_videos['youtube_' . $video_id] = true;

                // 説明文を生成（HTMLタグを除去してプレーンテキストに）
                $description = wp_strip_all_tags($post->post_content);
                $description = wp_trim_words($description, 30, '...');
                // HTMLエンティティをデコードしてからエスケープ
                $description = htmlspecialchars(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_XML1, 'UTF-8');

                $xml .= "\t\t<video:video>\n";
                $xml .= "\t\t\t<video:thumbnail_loc>" . esc_url('https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg') . "</video:thumbnail_loc>\n";
                $xml .= "\t\t\t<video:title>" . htmlspecialchars($post->post_title, ENT_XML1, 'UTF-8') . "</video:title>\n";
                $xml .= "\t\t\t<video:description>" . $description . "</video:description>\n";
                $xml .= "\t\t\t<video:player_loc>" . esc_url('https://www.youtube.com/watch?v=' . $video_id) . "</video:player_loc>\n";
                $xml .= "\t\t</video:video>\n";
            }
        }

        // 本文中のYouTube iframe埋め込みを検出
        preg_match_all('/<iframe[^>]+src=["\']https?:\/\/(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]+)[^"\']*["\']/i', $post->post_content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $video_id) {
                // 重複チェック
                if (isset($seen_videos['youtube_' . $video_id])) {
                    continue;
                }
                $seen_videos['youtube_' . $video_id] = true;

                // 説明文を生成（HTMLタグを除去してプレーンテキストに）
                $description = wp_strip_all_tags($post->post_content);
                $description = wp_trim_words($description, 30, '...');
                // HTMLエンティティをデコードしてからエスケープ
                $description = htmlspecialchars(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_XML1, 'UTF-8');

                $xml .= "\t\t<video:video>\n";
                $xml .= "\t\t\t<video:thumbnail_loc>" . esc_url('https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg') . "</video:thumbnail_loc>\n";
                $xml .= "\t\t\t<video:title>" . htmlspecialchars($post->post_title, ENT_XML1, 'UTF-8') . "</video:title>\n";
                $xml .= "\t\t\t<video:description>" . $description . "</video:description>\n";
                $xml .= "\t\t\t<video:player_loc>" . esc_url('https://www.youtube.com/watch?v=' . $video_id) . "</video:player_loc>\n";
                $xml .= "\t\t</video:video>\n";
            }
        }

        // 本文中のVimeo動画を検出（URL形式）
        preg_match_all('/vimeo\.com\/([0-9]+)/i', $post->post_content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $video_id) {
                // 重複チェック
                if (isset($seen_videos['vimeo_' . $video_id])) {
                    continue;
                }
                $seen_videos['vimeo_' . $video_id] = true;

                // 説明文を生成（HTMLタグを除去してプレーンテキストに）
                $description = wp_strip_all_tags($post->post_content);
                $description = wp_trim_words($description, 30, '...');
                // HTMLエンティティをデコードしてからエスケープ
                $description = htmlspecialchars(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_XML1, 'UTF-8');

                $xml .= "\t\t<video:video>\n";
                $xml .= "\t\t\t<video:title>" . htmlspecialchars($post->post_title, ENT_XML1, 'UTF-8') . "</video:title>\n";
                $xml .= "\t\t\t<video:description>" . $description . "</video:description>\n";
                $xml .= "\t\t\t<video:player_loc>" . esc_url('https://player.vimeo.com/video/' . $video_id) . "</video:player_loc>\n";
                $xml .= "\t\t</video:video>\n";
            }
        }

        // 本文中のVimeo iframe埋め込みを検出
        preg_match_all('/<iframe[^>]+src=["\']https?:\/\/player\.vimeo\.com\/video\/([0-9]+)[^"\']*["\']/i', $post->post_content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $video_id) {
                // 重複チェック
                if (isset($seen_videos['vimeo_' . $video_id])) {
                    continue;
                }
                $seen_videos['vimeo_' . $video_id] = true;

                // 説明文を生成（HTMLタグを除去してプレーンテキストに）
                $description = wp_strip_all_tags($post->post_content);
                $description = wp_trim_words($description, 30, '...');
                // HTMLエンティティをデコードしてからエスケープ
                $description = htmlspecialchars(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_XML1, 'UTF-8');

                $xml .= "\t\t<video:video>\n";
                $xml .= "\t\t\t<video:title>" . htmlspecialchars($post->post_title, ENT_XML1, 'UTF-8') . "</video:title>\n";
                $xml .= "\t\t\t<video:description>" . $description . "</video:description>\n";
                $xml .= "\t\t\t<video:player_loc>" . esc_url('https://player.vimeo.com/video/' . $video_id) . "</video:player_loc>\n";
                $xml .= "\t\t</video:video>\n";
            }
        }

        return $xml;
    }

    /**
     * 更新頻度を取得
     */
    private function get_change_frequency($post_type) {
        $settings = get_option('ksus_post_type_settings', array());

        if (isset($settings[$post_type]['changefreq'])) {
            return $settings[$post_type]['changefreq'];
        }

        // デフォルト値
        $defaults = array(
            'post' => 'weekly',
            'page' => 'monthly'
        );

        return isset($defaults[$post_type]) ? $defaults[$post_type] : 'monthly';
    }

    /**
     * 優先度を取得
     */
    private function get_priority($post_type) {
        $settings = get_option('ksus_post_type_settings', array());

        if (isset($settings[$post_type]['priority'])) {
            return number_format($settings[$post_type]['priority'], 1);
        }

        // デフォルト値
        $defaults = array(
            'post' => '0.8',
            'page' => '0.6'
        );

        return isset($defaults[$post_type]) ? $defaults[$post_type] : '0.5';
    }

    /**
     * ニュース投稿があるかチェック
     */
    private function has_news_posts() {
        // 設定から対象投稿タイプを取得
        $news_post_types = get_option('ksus_news_post_types', array());

        if (empty($news_post_types)) {
            return false;
        }

        // 除外でない投稿が少なくとも1つあるかチェック
        $args = array(
            'post_type' => $news_post_types,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_ksus_sitemap_type',
                    'value' => 'exclude',
                    'compare' => '!='
                ),
                array(
                    'key' => '_ksus_sitemap_type',
                    'compare' => 'NOT EXISTS'
                )
            )
        );

        $posts = get_posts($args);

        return !empty($posts);
    }

    /**
     * 対象となる投稿タイプを取得
     */
    private function get_allowed_post_types() {
        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment']);
        return apply_filters('ksus_allowed_post_types', $post_types);
    }

    /**
     * サイトマップをファイルに保存
     */
    private function save_sitemap($filename, $content) {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps';

        // ディレクトリが存在しない場合は作成
        if (!file_exists($sitemap_dir)) {
            wp_mkdir_p($sitemap_dir);
        }

        $file_path = $sitemap_dir . '/' . $filename;
        file_put_contents($file_path, $content);
    }

    /**
     * リライトルールを追加
     */
    public function add_rewrite_rules() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?ksus_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-([a-zA-Z0-9_-]+)\.xml$', 'index.php?ksus_sitemap=$matches[1]', 'top');
    }

    /**
     * クエリ変数を追加
     */
    public function add_query_vars($vars) {
        $vars[] = 'ksus_sitemap';
        return $vars;
    }

    /**
     * サイトマップのリダイレクトを無効化
     */
    public function disable_sitemap_redirect($redirect_url, $requested_url) {
        // サイトマップURLの場合はリダイレクトしない
        if (get_query_var('ksus_sitemap')) {
            return false;
        }
        return $redirect_url;
    }

    /**
     * サイトマップを配信
     */
    public function serve_sitemap() {
        $sitemap = get_query_var('ksus_sitemap');

        if (!$sitemap) {
            return;
        }

        // リダイレクトループを防ぐ
        remove_action('template_redirect', array($this, 'serve_sitemap'));

        $upload_dir = wp_upload_dir();
        $filename = ($sitemap === 'index') ? 'sitemap.xml' : 'sitemap-' . $sitemap . '.xml';
        $file_path = $upload_dir['basedir'] . '/sitemaps/' . $filename;

        if (file_exists($file_path)) {
            status_header(200);
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow', true);
            readfile($file_path);
            exit;
        } else {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
        }
    }

    /**
     * 投稿保存時にサイトマップを再生成（条件付き）
     */
    public function maybe_regenerate_sitemaps($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            $this->generate_all_sitemaps();
        }
    }
}
