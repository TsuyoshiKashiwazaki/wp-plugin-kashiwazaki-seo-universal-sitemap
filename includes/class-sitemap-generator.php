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
    // 1ファイルあたりのURL上限（sitemaps.org / Googleニュースサイトマップの仕様）
    const MAX_URLS_PER_FILE = 50000;
    const MAX_NEWS_URLS_PER_FILE = 1000;

    // Googleニュースサイトマップに載せる記事の期間（公開から2日以内）
    const NEWS_WINDOW_SECONDS = 172800;

    // 静的生成モードでニュースサイトマップを定期的に作り直すcronフック
    const NEWS_REFRESH_HOOK = 'ksus_refresh_news_sitemap';

    private static $instance = null;

    // このリクエストの終了時にサイトマップを再生成するか
    private $regeneration_pending = false;

    // 直近の生成でファイル書き込みに失敗したか
    private $write_failed = false;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('ksus_regenerate_sitemaps', array($this, 'generate_all_sitemaps'));
        add_action('transition_post_status', array($this, 'on_transition_post_status'), 10, 3);
        add_action('deleted_post', array($this, 'on_deleted_post'), 10, 2);
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('init', array($this, 'sync_news_refresh_schedule'));
        add_action(self::NEWS_REFRESH_HOOK, array($this, 'refresh_news_sitemap'));
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
        $this->write_failed = false;
        $this->generate_post_type_sitemaps();
        $this->generate_news_sitemap();
        $this->generate_index_sitemap();

        // すべてのファイルを書き込めた場合のみ true
        return !$this->write_failed;
    }

    /**
     * ニュースサイトマップとインデックスだけを作り直す（静的生成モードのcron用）
     *
     * 公開から2日を過ぎた記事を外すため、投稿の保存がなくても定期的に実行する。
     */
    public function refresh_news_sitemap() {
        if (get_option('ksus_generation_mode', 'static') === 'dynamic') {
            return;
        }

        $this->write_failed = false;
        $this->generate_news_sitemap();
        $this->generate_index_sitemap();
    }

    /**
     * ニュースサイトマップの定期再生成を、設定に合わせて登録・解除する
     */
    public function sync_news_refresh_schedule() {
        $needs_refresh = get_option('ksus_generation_mode', 'static') !== 'dynamic'
            && !empty(get_option('ksus_news_post_types', array()));
        $next = wp_next_scheduled(self::NEWS_REFRESH_HOOK);

        if ($needs_refresh && !$next) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::NEWS_REFRESH_HOOK);
        } elseif (!$needs_refresh && $next) {
            wp_clear_scheduled_hook(self::NEWS_REFRESH_HOOK);
        }
    }

    /**
     * 登録済みのcronイベントを解除する（プラグイン無効化時）
     */
    public static function clear_scheduled_events() {
        wp_clear_scheduled_hook(self::NEWS_REFRESH_HOOK);
    }

    /**
     * インデックスサイトマップを生成
     */
    private function generate_index_sitemap() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $home_url = home_url('/');
        // サイトのタイムゾーンでオフセット付きの時刻を出す（WordPress は PHP の既定タイムゾーンを UTC にするため date() は使わない）
        $lastmod = wp_date('c');
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

                // 次に分割ファイル（-2以降）を番号順に追加（.xml と .xml.gz 両方）
                foreach ($this->get_split_files($sitemap_dir, $post_type) as $split) {
                    $filename = basename($split['path']);
                    $xml .= "\t<sitemap>\n";
                    $xml .= "\t\t<loc>" . esc_url($home_url . $filename) . "</loc>\n";
                    $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
                    $xml .= "\t</sitemap>\n";
                    $has_sitemap = true;
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

            // 次に分割ファイル（-2以降）を番号順に追加（.xml と .xml.gz 両方）
            foreach ($this->get_split_files($sitemap_dir, 'googlenews') as $split) {
                $filename = basename($split['path']);
                $xml .= "\t<sitemap>\n";
                $xml .= "\t\t<loc>" . esc_url($home_url . $filename) . "</loc>\n";
                $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
                $xml .= "\t</sitemap>\n";
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

        // 古い分割ファイルは、今回のファイルをすべて書き込めた後に削除する（失敗時に既存のサイトマップを失わないため）
        $write_ok = true;

        $max_urls_per_file = self::MAX_URLS_PER_FILE; // Google推奨の上限
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
                        $write_ok = $this->save_sitemap('sitemap-' . $post_type . '.xml', $xml) && $write_ok;
                    } else {
                        // 2番目以降は -2, -3, -4...
                        $write_ok = $this->save_sitemap('sitemap-' . $post_type . '-' . $file_number . '.xml', $xml) && $write_ok;
                    }

                    $file_number++;
                    $current_file_url_count = 0;
                    $xml = $this->get_sitemap_xml_header();
                }

                $xml .= $this->build_post_url_entry($post, $post_type);
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
                $write_ok = $this->save_sitemap('sitemap-' . $post_type . '.xml', $xml) && $write_ok;
            } else {
                // 2番目以降のファイルは番号付き
                $write_ok = $this->save_sitemap('sitemap-' . $post_type . '-' . $file_number . '.xml', $xml) && $write_ok;
            }
        } elseif ($total_url_count === 0) {
            // 該当する投稿が0件の場合、既存のサイトマップファイルを削除
            $this->delete_sitemap_files($post_type);
            return;
        }

        // すべて書き込めた場合だけ、今回より後ろの番号の古い分割ファイルを削除
        if ($write_ok) {
            $this->cleanup_old_sitemap_files($post_type, $file_number);
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
     *
     * @param string $post_type  投稿タイプ
     * @param int    $keep_up_to この番号までの分割ファイルは残す（1 なら -2 以降をすべて削除）
     */
    private function cleanup_old_sitemap_files($post_type, $keep_up_to = 1) {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        if (!is_dir($sitemap_dir)) {
            return;
        }

        // 番号付きファイル（-2以降）を削除（.xml と .xml.gz 両方）
        foreach ($this->get_split_files($sitemap_dir, $post_type) as $split) {
            if ($split['number'] > $keep_up_to && file_exists($split['path'])) {
                unlink($split['path']);
            }
        }
    }

    /**
     * 「sitemap-{base}-{数字}」の分割ファイル（-2以降）を番号順に返す
     *
     * glob の * は別の投稿タイプ名（例: event に対する event-venue や event-2026）にも一致するため、
     * 数字だけの接尾辞に限り、さらに「{base}-{数字}」という投稿タイプが存在する場合はその本体ファイルとして除外する。
     *
     * @return array 各要素は array('number' => int, 'path' => string)
     */
    private function get_split_files($sitemap_dir, $base) {
        $split_files = array();
        $post_types = $this->get_allowed_post_types();

        foreach (array('xml', 'xml.gz') as $ext) {
            $files = glob($sitemap_dir . 'sitemap-' . $base . '-*.' . $ext);

            if (!$files) {
                continue;
            }

            foreach ($files as $file) {
                if (!preg_match('/^sitemap-' . preg_quote($base, '/') . '-(\d+)\.(xml|xml\.gz)$/', basename($file), $matches)) {
                    continue;
                }

                $number = (int) $matches[1];
                if ($number < 2 || in_array($base . '-' . $matches[1], $post_types, true)) {
                    continue;
                }

                $split_files[] = array('number' => $number, 'path' => $file);
            }
        }

        usort($split_files, function($a, $b) {
            return $a['number'] - $b['number'];
        });

        return $split_files;
    }

    /**
     * 投稿タイプのサイトマップファイルを全て削除
     */
    private function delete_sitemap_files($post_type) {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        if (!is_dir($sitemap_dir)) {
            return;
        }

        // 基本ファイル（番号なし）を削除
        foreach (array('xml', 'xml.gz') as $ext) {
            $file = $sitemap_dir . 'sitemap-' . $post_type . '.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // 番号付きファイルも削除
        $this->cleanup_old_sitemap_files($post_type);
    }

    /**
     * ニュースサイトマップを生成
     */
    private function generate_news_sitemap() {
        // 設定から対象投稿タイプを取得
        $news_post_types = get_option('ksus_news_post_types', array());

        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        if (empty($news_post_types)) {
            // ニュース設定が空の場合は分割ファイルも基本ファイルも削除
            $this->delete_news_sitemap_files();
            return;
        }

        // 古い分割ファイルは、今回のファイルをすべて書き込めた後に削除する（失敗時に既存のサイトマップを失わないため）
        $write_ok = true;

        $max_urls_per_file = self::MAX_NEWS_URLS_PER_FILE; // Googleニュースサイトマップの上限
        $batch_size = 100; // メモリ効率化のためのバッチサイズ
        $offset = 0;
        $file_number = 1; // 最初のファイルは番号なし、2番目から-2, -3...
        $current_file_url_count = 0;
        $total_url_count = 0;
        $xml = '';

        // XMLヘッダーを初期化
        $xml = $this->get_news_sitemap_xml_header();

        // 生成中に境界がずれないよう、期間の条件は最初に1回だけ作る
        $news_date_query = $this->get_news_date_query();

        while (true) {
            $args = array(
                'post_type' => $news_post_types,
                'post_status' => 'publish',
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'orderby' => 'date',
                'order' => 'DESC',
                'date_query' => $news_date_query
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
                        $write_ok = $this->save_sitemap('sitemap-googlenews.xml', $xml) && $write_ok;
                    } else {
                        // 2番目以降は -2, -3, -4...
                        $write_ok = $this->save_sitemap('sitemap-googlenews-' . $file_number . '.xml', $xml) && $write_ok;
                    }

                    $file_number++;
                    $current_file_url_count = 0;
                    $xml = $this->get_news_sitemap_xml_header();
                }

                $xml .= $this->build_news_url_entry($post);
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
                $write_ok = $this->save_sitemap('sitemap-googlenews.xml', $xml) && $write_ok;
            } else {
                // 2番目以降のファイルは番号付き
                $write_ok = $this->save_sitemap('sitemap-googlenews-' . $file_number . '.xml', $xml) && $write_ok;
            }
        } elseif ($total_url_count === 0) {
            // 該当する投稿が0件の場合、既存のニュースサイトマップファイルを削除
            $this->delete_news_sitemap_files();
            return;
        }

        // すべて書き込めた場合だけ、今回より後ろの番号の古い分割ファイルを削除
        if ($write_ok) {
            $this->cleanup_old_news_sitemap_files($file_number);
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
     *
     * @param int $keep_up_to この番号までの分割ファイルは残す（1 なら -2 以降をすべて削除）
     */
    private function cleanup_old_news_sitemap_files($keep_up_to = 1) {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        if (!is_dir($sitemap_dir)) {
            return;
        }

        // 番号付きファイル（-2以降）を削除（.xml と .xml.gz 両方）
        foreach ($this->get_split_files($sitemap_dir, 'googlenews') as $split) {
            if ($split['number'] > $keep_up_to && file_exists($split['path'])) {
                unlink($split['path']);
            }
        }
    }

    /**
     * ニュースサイトマップファイルを全て削除
     */
    private function delete_news_sitemap_files() {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';

        if (!is_dir($sitemap_dir)) {
            return;
        }

        // 基本ファイル（番号なし）を削除
        foreach (array('xml', 'xml.gz') as $ext) {
            $file = $sitemap_dir . 'sitemap-googlenews.' . $ext;
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // 番号付きファイルも削除
        $this->cleanup_old_news_sitemap_files();
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
        $youtube_ids = array();
        $vimeo_ids = array();

        // 本文中のYouTube動画を検出（URL形式）
        preg_match_all('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            $youtube_ids = array_merge($youtube_ids, $matches[1]);
        }

        // 本文中のYouTube iframe埋め込みを検出
        preg_match_all('/<iframe[^>]+src=["\']https?:\/\/(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]+)[^"\']*["\']/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            $youtube_ids = array_merge($youtube_ids, $matches[1]);
        }

        // 本文中のVimeo動画を検出（URL形式）
        preg_match_all('/vimeo\.com\/([0-9]+)/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            $vimeo_ids = array_merge($vimeo_ids, $matches[1]);
        }

        // 本文中のVimeo iframe埋め込みを検出
        preg_match_all('/<iframe[^>]+src=["\']https?:\/\/player\.vimeo\.com\/video\/([0-9]+)[^"\']*["\']/i', $post->post_content, $matches);
        if (!empty($matches[1])) {
            $vimeo_ids = array_merge($vimeo_ids, $matches[1]);
        }

        if (empty($youtube_ids) && empty($vimeo_ids)) {
            return $xml;
        }

        // 説明文を生成（HTMLタグを除去してプレーンテキストに）
        $description = wp_strip_all_tags($post->post_content);
        $description = wp_trim_words($description, 30, '...');
        // HTMLエンティティをデコードしてからエスケープ
        $description = htmlspecialchars(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_XML1, 'UTF-8');
        $title = htmlspecialchars($post->post_title, ENT_XML1, 'UTF-8');

        foreach ($youtube_ids as $video_id) {
            // 重複チェック
            if (isset($seen_videos['youtube_' . $video_id])) {
                continue;
            }
            $seen_videos['youtube_' . $video_id] = true;

            $xml .= "\t\t<video:video>\n";
            // hqdefault.jpg は高解像度でない動画にも用意される（maxresdefault.jpg はHD動画にしか無い）
            $xml .= "\t\t\t<video:thumbnail_loc>" . esc_url('https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg') . "</video:thumbnail_loc>\n";
            $xml .= "\t\t\t<video:title>" . $title . "</video:title>\n";
            $xml .= "\t\t\t<video:description>" . $description . "</video:description>\n";
            // player_loc はその動画のプレーヤーURL（埋め込み用URL）
            $xml .= "\t\t\t<video:player_loc>" . esc_url('https://www.youtube.com/embed/' . $video_id) . "</video:player_loc>\n";
            $xml .= "\t\t</video:video>\n";
        }

        foreach ($vimeo_ids as $video_id) {
            // 重複チェック
            if (isset($seen_videos['vimeo_' . $video_id])) {
                continue;
            }
            $seen_videos['vimeo_' . $video_id] = true;

            // video:thumbnail_loc は必須タグ。サムネイルを取得できない動画は出力しない
            $thumbnail_url = $this->get_vimeo_thumbnail_url($video_id);
            if ($thumbnail_url === '') {
                continue;
            }

            $xml .= "\t\t<video:video>\n";
            $xml .= "\t\t\t<video:thumbnail_loc>" . esc_url($thumbnail_url) . "</video:thumbnail_loc>\n";
            $xml .= "\t\t\t<video:title>" . $title . "</video:title>\n";
            $xml .= "\t\t\t<video:description>" . $description . "</video:description>\n";
            $xml .= "\t\t\t<video:player_loc>" . esc_url('https://player.vimeo.com/video/' . $video_id) . "</video:player_loc>\n";
            $xml .= "\t\t</video:video>\n";
        }

        return $xml;
    }

    /**
     * VimeoのサムネイルURLを oEmbed API から取得する（結果はtransientにキャッシュ）
     *
     * 取得できない場合は空文字を返す。失敗も1日キャッシュして、生成のたびに問い合わせない。
     */
    private function get_vimeo_thumbnail_url($video_id) {
        $video_id = preg_replace('/[^0-9]/', '', (string) $video_id);
        if ($video_id === '') {
            return '';
        }

        $cache_key = 'ksus_vimeo_thumb_' . $video_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return (string) $cached;
        }

        $thumbnail_url = '';
        $response = wp_safe_remote_get(
            'https://vimeo.com/api/oembed.json?url=' . rawurlencode('https://vimeo.com/' . $video_id),
            array(
                'timeout' => 5,
                'redirection' => 0
            )
        );

        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($data) && isset($data['thumbnail_url']) && is_string($data['thumbnail_url'])) {
                $thumbnail_url = esc_url_raw($data['thumbnail_url'], array('https'));
            }
        }

        set_transient($cache_key, $thumbnail_url, $thumbnail_url !== '' ? 30 * DAY_IN_SECONDS : DAY_IN_SECONDS);

        return $thumbnail_url;
    }

    /**
     * 投稿タイプ別サイトマップの <url> 要素を1件分組み立てる（静的・動的で共通）
     */
    private function build_post_url_entry($post, $post_type) {
        $xml = "\t<url>\n";
        $xml .= "\t\t<loc>" . esc_url(get_permalink($post->ID)) . "</loc>\n";
        $xml .= "\t\t<lastmod>" . get_post_modified_time('c', false, $post) . "</lastmod>\n";
        $xml .= "\t\t<changefreq>" . $this->get_change_frequency($post_type) . "</changefreq>\n";
        $xml .= "\t\t<priority>" . $this->get_priority($post_type) . "</priority>\n";
        $xml .= $this->build_media_xml($post);
        $xml .= "\t</url>\n";

        return $xml;
    }

    /**
     * ニュースサイトマップの <url> 要素を1件分組み立てる（静的・動的で共通）
     */
    private function build_news_url_entry($post) {
        $xml = "\t<url>\n";
        $xml .= "\t\t<loc>" . esc_url(get_permalink($post->ID)) . "</loc>\n";
        $xml .= "\t\t<news:news>\n";
        $xml .= "\t\t\t<news:publication>\n";
        $xml .= "\t\t\t\t<news:name>" . htmlspecialchars(get_bloginfo('name'), ENT_XML1, 'UTF-8') . "</news:name>\n";
        $xml .= "\t\t\t\t<news:language>" . htmlspecialchars($this->get_news_language(), ENT_XML1, 'UTF-8') . "</news:language>\n";
        $xml .= "\t\t\t</news:publication>\n";
        $xml .= "\t\t\t<news:publication_date>" . get_post_time('c', false, $post) . "</news:publication_date>\n";
        $xml .= "\t\t\t<news:title>" . htmlspecialchars($post->post_title, ENT_XML1, 'UTF-8') . "</news:title>\n";
        $xml .= "\t\t</news:news>\n";
        $xml .= $this->build_media_xml($post);
        $xml .= "\t</url>\n";

        return $xml;
    }

    /**
     * 設定に基づいて画像・動画情報のXMLを返す
     *
     * パスワード保護された投稿は、本文由来の情報（画像・動画の説明文）を公開サイトマップに出さない。
     */
    private function build_media_xml($post) {
        if (!empty($post->post_password)) {
            return '';
        }

        $xml = '';

        if (get_option('ksus_include_images', true)) {
            $xml .= $this->get_images_xml($post);
        }

        if (get_option('ksus_include_videos', true)) {
            $xml .= $this->get_videos_xml($post);
        }

        return $xml;
    }

    /**
     * news:language 用の言語コードを返す
     *
     * Googleニュースサイトマップは ISO 639 の言語コード（2〜3文字）を要求する。
     * 中国語のみ例外で、簡体字は zh-cn、繁体字は zh-tw。
     */
    private function get_news_language() {
        $language = strtolower(str_replace('_', '-', (string) get_bloginfo('language')));

        if ($language === 'zh' || strpos($language, 'zh-') === 0) {
            return preg_match('/^zh-(tw|hk|mo|hant)(-|$)/', $language) ? 'zh-tw' : 'zh-cn';
        }

        $parts = explode('-', $language);
        if (preg_match('/^[a-z]{2,3}$/', $parts[0])) {
            return $parts[0];
        }

        return 'en';
    }

    /**
     * ニュースサイトマップに載せる期間（公開から2日以内）の date_query を返す
     *
     * post_date_gmt と比較するため、日時は配列で渡す（文字列で渡すとサイトのタイムゾーンとして解釈される）。
     */
    private function get_news_date_query() {
        $threshold = time() - self::NEWS_WINDOW_SECONDS;

        return array(
            array(
                'column' => 'post_date_gmt',
                'after' => array(
                    'year' => (int) gmdate('Y', $threshold),
                    'month' => (int) gmdate('n', $threshold),
                    'day' => (int) gmdate('j', $threshold),
                    'hour' => (int) gmdate('G', $threshold),
                    'minute' => (int) gmdate('i', $threshold),
                    'second' => (int) gmdate('s', $threshold)
                ),
                'inclusive' => true
            )
        );
    }

    /**
     * 「サイトマップから除外」されていない投稿だけに絞る meta_query を返す
     */
    private function get_not_excluded_meta_query() {
        return array(
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
        );
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
        return $this->count_news_posts() > 0;
    }

    /**
     * ニュースサイトマップに載せる投稿（公開から2日以内・除外なし）の件数
     */
    private function count_news_posts() {
        // 設定から対象投稿タイプを取得
        $news_post_types = get_option('ksus_news_post_types', array());

        if (empty($news_post_types)) {
            return 0;
        }

        $query = new WP_Query(array(
            'post_type' => $news_post_types,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'date_query' => $this->get_news_date_query(),
            'meta_query' => $this->get_not_excluded_meta_query(),
            'ignore_sticky_posts' => true,
            'suppress_filters' => true
        ));

        return (int) $query->found_posts;
    }

    /**
     * サイトマップ生成が有効な投稿タイプを返す
     */
    private function get_enabled_post_types() {
        $post_types = $this->get_allowed_post_types();
        $enabled_post_types = get_option('ksus_enabled_post_types', false);

        // 初回のみ全て有効
        if ($enabled_post_types === false) {
            return array_values($post_types);
        }

        return array_values(array_intersect((array) $enabled_post_types, $post_types));
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
     *
     * ローカルのファイルシステムでは一時ファイルに書いてから置き換えるので、配信中のファイルが書きかけになることはない。
     * 失敗した場合は false を返し、既存のファイルは残す。
     */
    private function save_sitemap($filename, $content) {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/sitemaps';

        // ディレクトリが存在しない場合は作成
        if (!file_exists($sitemap_dir) && !wp_mkdir_p($sitemap_dir)) {
            return $this->record_write_failure($sitemap_dir, 'mkdir');
        }

        $enable_gzip = get_option('ksus_enable_gzip', false);

        if ($enable_gzip) {
            // GZIP圧縮して保存
            $data = gzencode($content, 9); // 最高圧縮レベル
            if ($data === false) {
                return $this->record_write_failure($filename, 'gzip');
            }
            $file_path = $sitemap_dir . '/' . $filename . '.gz';
            // 古いXMLファイル
            $stale_file = $sitemap_dir . '/' . $filename;
        } else {
            // 通常のXMLファイルとして保存
            $data = $content;
            $file_path = $sitemap_dir . '/' . $filename;
            // 古いGZファイル
            $stale_file = $sitemap_dir . '/' . $filename . '.gz';
        }

        if (wp_is_stream($file_path)) {
            // ストリームラッパー（S3 等）の保存先では一時ファイルの rename が使えない場合があるため、直接書き込む
            $written = @file_put_contents($file_path, $data);

            if ($written === false || $written !== strlen($data)) {
                return $this->record_write_failure($file_path, 'write');
            }
        } else {
            // 一時ファイル名はリクエストごとに異なるため排他ロックは不要
            $temp_file = $file_path . '.tmp-' . str_replace('.', '', uniqid('', true));
            $written = @file_put_contents($temp_file, $data);

            if ($written === false || $written !== strlen($data)) {
                if (file_exists($temp_file)) {
                    @unlink($temp_file);
                }
                return $this->record_write_failure($file_path, 'write');
            }

            if (!@rename($temp_file, $file_path)) {
                @unlink($temp_file);
                return $this->record_write_failure($file_path, 'rename');
            }
        }

        if (file_exists($stale_file)) {
            @unlink($stale_file);
        }

        return true;
    }

    /**
     * ファイル書き込みの失敗を記録する
     */
    private function record_write_failure($path, $step) {
        $this->write_failed = true;
        error_log(sprintf('[Kashiwazaki SEO Universal Sitemap] サイトマップの書き込みに失敗しました (%s): %s', $step, $path));
        return false;
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

        $generation_mode = get_option('ksus_generation_mode', 'static');

        // 動的生成モード
        if ($generation_mode === 'dynamic') {
            $this->serve_dynamic_sitemap($sitemap);
            return;
        }

        // 静的生成モード（従来の動作）
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
     * 動的にサイトマップを生成して配信
     */
    private function serve_dynamic_sitemap($sitemap) {
        $xml = '';
        $request = $this->resolve_dynamic_request($sitemap);

        if ($request !== null) {
            if ($request['type'] === 'index') {
                $xml = $this->generate_dynamic_index_sitemap();
            } elseif ($request['type'] === 'news') {
                $xml = $this->generate_dynamic_news_sitemap($request['page']);
            } else {
                $xml = $this->generate_dynamic_post_type_sitemap($request['post_type'], $request['page']);
            }
        }

        if (empty($xml)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow', true);
        echo $xml;
        exit;
    }

    /**
     * 動的モードのリクエスト名を「種類・投稿タイプ・ページ番号」に解釈する
     *
     * sitemap-{名前}-{数字}.xml は、「{名前}-{数字}」という投稿タイプが有効ならその1ページ目、
     * そうでなければ「{名前}」の {数字} ページ目（2以上）として扱う。
     */
    private function resolve_dynamic_request($sitemap) {
        if ($sitemap === 'index') {
            return array('type' => 'index', 'page' => 1);
        }

        $enabled_post_types = $this->get_enabled_post_types();

        if (in_array($sitemap, $enabled_post_types, true)) {
            return array('type' => 'post_type', 'post_type' => $sitemap, 'page' => 1);
        }

        if ($sitemap === 'googlenews') {
            return array('type' => 'news', 'page' => 1);
        }

        if (preg_match('/^(.+)-(\d+)$/', $sitemap, $matches)) {
            $page = (int) $matches[2];
            if ($page < 2) {
                return null;
            }

            if ($matches[1] === 'googlenews') {
                return array('type' => 'news', 'page' => $page);
            }

            if (in_array($matches[1], $enabled_post_types, true)) {
                return array('type' => 'post_type', 'post_type' => $matches[1], 'page' => $page);
            }
        }

        return null;
    }

    /**
     * 動的にインデックスサイトマップを生成
     */
    private function generate_dynamic_index_sitemap() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $home_url = home_url('/');
        // サイトのタイムゾーンでオフセット付きの時刻を出す（WordPress は PHP の既定タイムゾーンを UTC にするため date() は使わない）
        $lastmod = wp_date('c');

        // 投稿タイプ別サイトマップ（50,000件ごとに分割）
        foreach ($this->get_enabled_post_types() as $post_type) {
            $count = $this->count_posts_for_sitemap($post_type);
            $pages = (int) ceil($count / self::MAX_URLS_PER_FILE);

            for ($page = 1; $page <= $pages; $page++) {
                $filename = ($page === 1) ? 'sitemap-' . $post_type . '.xml' : 'sitemap-' . $post_type . '-' . $page . '.xml';
                $xml .= "\t<sitemap>\n";
                $xml .= "\t\t<loc>" . esc_url($home_url . $filename) . "</loc>\n";
                $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
                $xml .= "\t</sitemap>\n";
            }
        }

        // ニュースサイトマップ（1,000件ごとに分割）
        $news_count = $this->count_news_posts();
        $news_pages = (int) ceil($news_count / self::MAX_NEWS_URLS_PER_FILE);

        for ($page = 1; $page <= $news_pages; $page++) {
            $filename = ($page === 1) ? 'sitemap-googlenews.xml' : 'sitemap-googlenews-' . $page . '.xml';
            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>" . esc_url($home_url . $filename) . "</loc>\n";
            $xml .= "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
            $xml .= "\t</sitemap>\n";
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * 動的に投稿タイプサイトマップを生成（1ページ最大50,000件、500件ずつ取得）
     */
    private function generate_dynamic_post_type_sitemap($post_type, $page = 1) {
        // 投稿タイプが有効か確認
        if (!in_array($post_type, $this->get_enabled_post_types(), true)) {
            return '';
        }

        $xml = $this->get_sitemap_xml_header();
        $batch_size = 500;
        $page_offset = ($page - 1) * self::MAX_URLS_PER_FILE;
        $fetched = 0;

        while ($fetched < self::MAX_URLS_PER_FILE) {
            $limit = min($batch_size, self::MAX_URLS_PER_FILE - $fetched);
            $posts = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'offset' => $page_offset + $fetched,
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_query' => $this->get_not_excluded_meta_query(),
                'no_found_rows' => true
            ));

            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                $xml .= $this->build_post_url_entry($post, $post_type);
            }

            $fetched += count($posts);

            if (count($posts) < $limit) {
                break;
            }
        }

        // 2ページ目以降で該当がなければ存在しないページ
        if ($page > 1 && $fetched === 0) {
            return '';
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * 動的にニュースサイトマップを生成（公開から2日以内、1ページ最大1,000件）
     */
    private function generate_dynamic_news_sitemap($page = 1) {
        $news_post_types = get_option('ksus_news_post_types', array());

        if (empty($news_post_types)) {
            return '';
        }

        $xml = $this->get_news_sitemap_xml_header();
        $batch_size = 100;
        $page_offset = ($page - 1) * self::MAX_NEWS_URLS_PER_FILE;
        $fetched = 0;
        $news_date_query = $this->get_news_date_query();

        while ($fetched < self::MAX_NEWS_URLS_PER_FILE) {
            $limit = min($batch_size, self::MAX_NEWS_URLS_PER_FILE - $fetched);
            $posts = get_posts(array(
                'post_type' => $news_post_types,
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'offset' => $page_offset + $fetched,
                'orderby' => 'date',
                'order' => 'DESC',
                'date_query' => $news_date_query,
                'meta_query' => $this->get_not_excluded_meta_query(),
                'no_found_rows' => true
            ));

            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                $xml .= $this->build_news_url_entry($post);
            }

            $fetched += count($posts);

            if (count($posts) < $limit) {
                break;
            }
        }

        // 2ページ目以降で該当がなければ存在しないページ
        if ($page > 1 && $fetched === 0) {
            return '';
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * サイトマップ用の投稿数をカウント
     */
    private function count_posts_for_sitemap($post_type) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_ksus_sitemap_type'
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value != 'exclude')",
            $post_type
        ));

        return (int) $count;
    }

    /**
     * 投稿の公開状態が変わったときに再生成を予約する（静的生成モードのみ）
     *
     * 公開・更新だけでなく、公開中の投稿を下書き・非公開・ゴミ箱に移した場合も対象。
     * 保存のたびに実行せず、リクエストの最後に1回だけ再生成する。
     */
    public function on_transition_post_status($new_status, $old_status, $post) {
        if ($new_status !== 'publish' && $old_status !== 'publish') {
            return;
        }

        if (!($post instanceof WP_Post)) {
            return;
        }

        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        if (!in_array($post->post_type, $this->get_allowed_post_types(), true)) {
            return;
        }

        $this->schedule_regeneration();
    }

    /**
     * 公開中の投稿が完全に削除されたときに再生成を予約する（静的生成モードのみ）
     */
    public function on_deleted_post($post_id, $post = null) {
        if (!($post instanceof WP_Post) || $post->post_status !== 'publish') {
            return;
        }

        if (!in_array($post->post_type, $this->get_allowed_post_types(), true)) {
            return;
        }

        $this->schedule_regeneration();
    }

    /**
     * 投稿保存時にサイトマップを再生成（条件付き）
     *
     * 以前のバージョンとの互換のために残している。
     */
    public function maybe_regenerate_sitemaps($post_id) {
        $post = get_post($post_id);
        if ($post) {
            $this->on_transition_post_status($post->post_status, $post->post_status, $post);
        }
    }

    /**
     * このリクエストの終了時に再生成する予約を入れる
     */
    private function schedule_regeneration() {
        // 動的モードの場合は静的ファイルを生成しない
        if (get_option('ksus_generation_mode', 'static') === 'dynamic') {
            return;
        }

        if ($this->regeneration_pending) {
            return;
        }

        $this->regeneration_pending = true;
        add_action('shutdown', array($this, 'run_pending_regeneration'));
    }

    /**
     * 予約された再生成を実行する
     */
    public function run_pending_regeneration() {
        if (!$this->regeneration_pending) {
            return;
        }

        $this->regeneration_pending = false;

        if (get_option('ksus_generation_mode', 'static') === 'dynamic') {
            return;
        }

        $this->generate_all_sitemaps();
    }
}
