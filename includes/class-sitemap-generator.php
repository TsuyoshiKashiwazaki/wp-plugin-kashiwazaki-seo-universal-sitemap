<?php
/**
 * サイトマップ生成クラス
 *
 * @package Kashiwazaki SEO Universal Sitemap
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
        add_action('wp_head', array($this, 'add_sitemap_to_head'), 1);
    }

    /**
     * HTML headにサイトマップリンクを追加
     */
    public function add_sitemap_to_head() {
        // 設定がONの場合のみ追加
        if (!get_option('ksus_enable_head_link', true)) {
            return;
        }

        $home_url = home_url('/');
        // GZIP有効時は.xml.gzを出力
        $extension = get_option('ksus_enable_gzip', false) ? '.xml.gz' : '.xml';
        $sitemap_url = $home_url . 'sitemap' . $extension;
        echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="' . esc_url($sitemap_url) . '" />' . "\n";
    }

    /**
     * サイトマップディレクトリのパスを取得
     */
    private function get_sitemap_dir() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/sitemaps/';
    }

    /**
     * すべてのサイトマップを生成
     */
    public function generate_all_sitemaps() {
        $this->generate_post_type_sitemaps();
        $this->generate_news_sitemap();
        $this->generate_index_sitemap();
    }

    /**
     * インデックスサイトマップを生成
     */
    private function generate_index_sitemap() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $home_url = home_url('/');
        $lastmod = date('c', current_time('timestamp'));
        $sitemap_dir = $this->get_sitemap_dir();
        $file_ext = $this->get_file_extension();

        // 投稿タイプ別サイトマップ（分割ファイル対応）
        $post_types = $this->get_allowed_post_types();
        $enabled_post_types = get_option('ksus_enabled_post_types', false);

        // 初回のみ全て有効
        if ($enabled_post_types === false) {
            $enabled_post_types = $post_types;
        }

        foreach ($post_types as $post_type) {
            // 有効な投稿タイプのみ処理
            if (in_array($post_type, $enabled_post_types)) {
                $has_sitemap = false;

                // まず番号なしファイル（常に最初）を確認
                $single_file = $this->file_exists_either($sitemap_dir, 'sitemap-' . $post_type);
                if ($single_file) {
                    $filename = basename($single_file);
                    $xml .= "\t<sitemap>\n";
                    $xml .= "\t\t<loc>" . esc_url($home_url . $filename) . "</loc>\n";
                    $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
                    $xml .= "\t</sitemap>\n";
                    $has_sitemap = true;
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
                        $xml .= "\t<sitemap>\n";
                        $xml .= "\t\t<loc>" . esc_url($home_url . $filename) . "</loc>\n";
                        $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
                        $xml .= "\t</sitemap>\n";
                        $has_sitemap = true;
                    }
                }
            }
        }

        // ニュースサイトマップ（分割ファイル対応）
        if ($this->has_news_posts()) {
            // まず番号なしファイル（常に最初）を確認
            $single_file = $this->file_exists_either($sitemap_dir, 'sitemap-googlenews');
            if ($single_file) {
                $filename = basename($single_file);
                $xml .= "\t<sitemap>\n";
                $xml .= "\t\t<loc>" . esc_url($home_url . $filename) . "</loc>\n";
                $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
                $xml .= "\t</sitemap>\n";
            }

            // 次に分割ファイル（-2以降）を検索（数字のみ、.xml と .xml.gz 両方）
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
                    $xml .= "\t<sitemap>\n";
                    $xml .= "\t\t<loc>" . esc_url($home_url . $filename) . "</loc>\n";
                    $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
                    $xml .= "\t</sitemap>\n";
                }
            }
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
                // 無効な投稿タイプのサイトマップファイルを削除（.xml と .xml.gz 両方）
                $xml_file = $sitemap_dir . 'sitemap-' . $post_type . '.xml';
                $gz_file = $sitemap_dir . 'sitemap-' . $post_type . '.xml.gz';

                if (file_exists($xml_file)) {
                    unlink($xml_file);
                }
                if (file_exists($gz_file)) {
                    unlink($gz_file);
                }
            }
        }
    }

    /**
     * 特定の投稿タイプのサイトマップを生成
     */
    private function generate_post_type_sitemap($post_type) {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        // 古い分割ファイルを削除（番号なしファイルは残す）
        $this->cleanup_old_sitemap_files($post_type);

        $max_urls_per_file = 50000; // Google推奨の上限
        $batch_size = 500; // メモリ効率化のためのバッチサイズ
        $offset = 0;
        $file_number = 1; // 最初のファイルは番号なし、2番目から-2, -3...
        $current_file_url_count = 0;
        $total_url_count = 0;
        $xml = '';

        // XMLヘッダーを初期化
        $xml = $this->get_sitemap_xml_header();

        while (true) {
            $args = array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'orderby' => 'ID',
                'order' => 'ASC'
            );

            $posts = get_posts($args);

            // 投稿がなくなったら終了
            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                $sitemap_type = get_post_meta($post->ID, '_ksus_sitemap_type', true);

                // 除外は含めない
                if ($sitemap_type === 'exclude') {
                    continue;
                }

                // 上限に達したら現在のファイルを保存して新しいファイルを開始
                if ($current_file_url_count >= $max_urls_per_file) {
                    $xml .= '</urlset>';

                    // ファイルを保存
                    if ($file_number === 1) {
                        // 最初のファイルは番号なし
                        $this->save_sitemap('sitemap-' . $post_type . '.xml', $xml);
                    } else {
                        // 2番目以降は -2, -3, -4...
                        $this->save_sitemap('sitemap-' . $post_type . '-' . $file_number . '.xml', $xml);
                    }

                    $file_number++;
                    $current_file_url_count = 0;
                    $xml = $this->get_sitemap_xml_header();
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
                $current_file_url_count++;
                $total_url_count++;
            }

            $offset += $batch_size;
        }

        // 最後のファイルを保存
        if ($current_file_url_count > 0) {
            $xml .= '</urlset>';

            if ($file_number === 1) {
                // 最初のファイル（50,000件以下の場合）は番号なし
                $this->save_sitemap('sitemap-' . $post_type . '.xml', $xml);
            } else {
                // 2番目以降のファイルは番号付き
                $this->save_sitemap('sitemap-' . $post_type . '-' . $file_number . '.xml', $xml);
            }
        }
    }

    /**
     * サイトマップXMLヘッダーを取得
     */
    private function get_sitemap_xml_header() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
        $xml .= 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        return $xml;
    }

    /**
     * 古いサイトマップファイルをクリーンアップ
     */
    private function cleanup_old_sitemap_files($post_type) {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        if (!is_dir($sitemap_dir)) {
            return;
        }

        // 番号付きファイル（-2以降）を削除（.xml と .xml.gz 両方）
        foreach (array('xml', 'xml.gz') as $ext) {
            $pattern = $sitemap_dir . 'sitemap-' . $post_type . '-*.' . $ext;
            $files = glob($pattern);

            if ($files) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
        }
    }

    /**
     * ニュースサイトマップを生成
     */
    private function generate_news_sitemap() {
        // 設定から対象投稿タイプを取得
        $news_post_types = get_option('ksus_news_post_types', array());

        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        // 古い分割ファイルを削除
        $this->cleanup_old_news_sitemap_files();

        if (empty($news_post_types)) {
            // ニュース設定が空の場合は基本ファイルも削除
            foreach (array('xml', 'xml.gz') as $ext) {
                $file = $sitemap_dir . 'sitemap-googlenews.' . $ext;
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            return;
        }

        $max_urls_per_file = 1000; // Googleニュースサイトマップの上限
        $batch_size = 100; // メモリ効率化のためのバッチサイズ
        $offset = 0;
        $file_number = 1; // 最初のファイルは番号なし、2番目から-2, -3...
        $current_file_url_count = 0;
        $total_url_count = 0;
        $xml = '';

        // XMLヘッダーを初期化
        $xml = $this->get_news_sitemap_xml_header();

        while (true) {
            $args = array(
                'post_type' => $news_post_types,
                'post_status' => 'publish',
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'orderby' => 'date',
                'order' => 'DESC'
            );

            $posts = get_posts($args);

            // 投稿がなくなったら終了
            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                $sitemap_type = get_post_meta($post->ID, '_ksus_sitemap_type', true);

                // 除外は含めない
                if ($sitemap_type === 'exclude') {
                    continue;
                }

                // 上限に達したら現在のファイルを保存して新しいファイルを開始
                if ($current_file_url_count >= $max_urls_per_file) {
                    $xml .= '</urlset>';

                    // ファイルを保存
                    if ($file_number === 1) {
                        // 最初のファイルは番号なし
                        $this->save_sitemap('sitemap-googlenews.xml', $xml);
                    } else {
                        // 2番目以降は -2, -3, -4...
                        $this->save_sitemap('sitemap-googlenews-' . $file_number . '.xml', $xml);
                    }

                    $file_number++;
                    $current_file_url_count = 0;
                    $xml = $this->get_news_sitemap_xml_header();
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
                $current_file_url_count++;
                $total_url_count++;
            }

            $offset += $batch_size;
        }

        // 最後のファイルを保存
        if ($current_file_url_count > 0) {
            $xml .= '</urlset>';

            if ($file_number === 1) {
                // 最初のファイル（100件以下の場合）は番号なし
                $this->save_sitemap('sitemap-googlenews.xml', $xml);
            } else {
                // 2番目以降のファイルは番号付き
                $this->save_sitemap('sitemap-googlenews-' . $file_number . '.xml', $xml);
            }
        }
    }

    /**
     * ニュースサイトマップXMLヘッダーを取得
     */
    private function get_news_sitemap_xml_header() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ';
        $xml .= 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" ';
        $xml .= 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" ';
        $xml .= 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        return $xml;
    }

    /**
     * 古いニュースサイトマップファイルをクリーンアップ
     */
    private function cleanup_old_news_sitemap_files() {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        if (!is_dir($sitemap_dir)) {
            return;
        }

        // 番号付きファイル（-2以降）を削除（.xml と .xml.gz 両方）
        foreach (array('xml', 'xml.gz') as $ext) {
            $pattern = $sitemap_dir . 'sitemap-googlenews-*.' . $ext;
            $files = glob($pattern);

            if ($files) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
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
                $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                $title_text = $alt_text !== '' ? $alt_text : get_the_title($thumbnail_id);
                $images[] = array(
                    'loc' => $image_url,
                    'title' => $title_text,
                    'caption' => wp_strip_all_tags(wp_get_attachment_caption($thumbnail_id))
                );
                $seen_urls[$image_url] = true;
            }
        }

        // 本文中の画像
        preg_match_all('/<img[^>]+>/i', $post->post_content, $img_tags);
        if (!empty($img_tags[0])) {
            foreach ($img_tags[0] as $img_tag) {
                if (!preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                    continue;
                }

                $img_url = $src_match[1];
                // 重複チェック
                if (!isset($seen_urls[$img_url])) {
                    $title_text = '';
                    $caption_text = '';

                    $attachment_id = attachment_url_to_postid($img_url);
                    if ($attachment_id) {
                        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                        if ($alt_text !== '') {
                            $title_text = $alt_text;
                        } else {
                            $title_text = get_the_title($attachment_id);
                        }
                        $caption_text = wp_strip_all_tags(wp_get_attachment_caption($attachment_id));
                    } else {
                        if (preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match)) {
                            $title_text = $alt_match[1];
                        } elseif (preg_match('/title=["\']([^"\']*)["\']/', $img_tag, $title_match)) {
                            $title_text = $title_match[1];
                        } else {
                            $path = parse_url($img_url, PHP_URL_PATH);
                            $title_text = $path ? wp_basename($path) : '';
                        }
                    }

                    $images[] = array(
                        'loc' => $img_url,
                        'title' => $title_text,
                        'caption' => $caption_text
                    );
                    $seen_urls[$img_url] = true;
                }
            }
        }

        // 画像を追加
        foreach ($images as $image) {
            $xml .= "\t\t<image:image>\n";
            $xml .= "\t\t\t<image:loc>" . esc_url($image['loc']) . "</image:loc>\n";

            // title: HTMLデコード→トリミング→空文字列チェック→XMLエスケープ
            if (isset($image['title']) && $image['title'] !== '') {
                $title = html_entity_decode($image['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = trim($title);
                if ($title !== '') {
                    $title = htmlspecialchars($title, ENT_XML1, 'UTF-8');
                    $xml .= "\t\t\t<image:title>" . $title . "</image:title>\n";
                }
            }

            // caption: HTMLデコード→トリミング→空文字列チェック→XMLエスケープ
            if (isset($image['caption']) && $image['caption'] !== '') {
                $caption = html_entity_decode($image['caption'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $caption = trim($caption);
                if ($caption !== '') {
                    $caption = htmlspecialchars($caption, ENT_XML1, 'UTF-8');
                    $xml .= "\t\t\t<image:caption>" . $caption . "</image:caption>\n";
                }
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
     * ファイル拡張子を取得（GZIP設定に応じて）
     */
    private function get_file_extension() {
        return get_option('ksus_enable_gzip', false) ? '.xml.gz' : '.xml';
    }

    /**
     * ファイルが存在するかチェック（.xml と .xml.gz 両方）
     */
    private function file_exists_either($sitemap_dir, $base_filename) {
        $xml_file = $sitemap_dir . $base_filename . '.xml';
        $gz_file = $sitemap_dir . $base_filename . '.xml.gz';

        if (file_exists($gz_file)) {
            return $gz_file;
        } elseif (file_exists($xml_file)) {
            return $xml_file;
        }

        return false;
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

        $enable_gzip = get_option('ksus_enable_gzip', false);

        if ($enable_gzip) {
            // GZIP圧縮して保存
            $file_path = $sitemap_dir . '/' . $filename . '.gz';
            $gz = gzopen($file_path, 'w9'); // 最高圧縮レベル
            gzwrite($gz, $content);
            gzclose($gz);

            // 古いXMLファイルを削除
            $old_xml = $sitemap_dir . '/' . $filename;
            if (file_exists($old_xml)) {
                unlink($old_xml);
            }
        } else {
            // 通常のXMLファイルとして保存
            $file_path = $sitemap_dir . '/' . $filename;
            file_put_contents($file_path, $content);

            // 古いGZファイルを削除
            $old_gz = $sitemap_dir . '/' . $filename . '.gz';
            if (file_exists($old_gz)) {
                unlink($old_gz);
            }
        }
    }

    /**
     * リライトルールを追加
     */
    public function add_rewrite_rules() {
        // GZIP形式(.xml.gz)のサポート
        add_rewrite_rule('^sitemap\.xml\.gz$', 'index.php?ksus_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-([a-zA-Z0-9_-]+-[0-9]+)\.xml\.gz$', 'index.php?ksus_sitemap=$matches[1]', 'top');
        add_rewrite_rule('^sitemap-([a-zA-Z0-9_-]+)\.xml\.gz$', 'index.php?ksus_sitemap=$matches[1]', 'top');

        // 通常のXML形式
        add_rewrite_rule('^sitemap\.xml$', 'index.php?ksus_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-([a-zA-Z0-9_-]+-[0-9]+)\.xml$', 'index.php?ksus_sitemap=$matches[1]', 'top');
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
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';
        $base_filename = ($sitemap === 'index') ? 'sitemap.xml' : 'sitemap-' . $sitemap . '.xml';

        // GZIPファイルを優先的にチェック
        $gz_file = $sitemap_dir . $base_filename . '.gz';
        $xml_file = $sitemap_dir . $base_filename;

        if (file_exists($gz_file)) {
            // GZIPファイルを配信
            status_header(200);
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Encoding: gzip');
            header('X-Robots-Tag: noindex, follow', true);
            readfile($gz_file);
            exit;
        } elseif (file_exists($xml_file)) {
            // 通常のXMLファイルを配信
            status_header(200);
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow', true);
            readfile($xml_file);
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
