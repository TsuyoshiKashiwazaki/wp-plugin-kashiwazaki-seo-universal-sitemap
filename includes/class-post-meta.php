<?php
/**
 * 投稿メタボックス管理クラス
 *
 * @package Kashiwazaki SEO Universal Sitemap
 */

if (!defined('ABSPATH')) {
    exit;
}

class KSUS_Post_Meta {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));
    }

    /**
     * メタボックスを追加
     */
    public function add_meta_box() {
        $post_types = $this->get_allowed_post_types();

        foreach ($post_types as $post_type) {
            add_meta_box(
                'ksus_sitemap_settings',
                'Kashiwazaki SEO Universal Sitemap',
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * 対象となる投稿タイプを取得
     */
    private function get_allowed_post_types() {
        $post_types = get_post_types(array('public' => true), 'names');

        // 添付ファイルは除外
        unset($post_types['attachment']);

        return apply_filters('ksus_allowed_post_types', $post_types);
    }

    /**
     * メタボックスのHTML出力
     */
    public function render_meta_box($post) {
        wp_nonce_field('ksus_save_meta_box', 'ksus_meta_box_nonce');

        $sitemap_type = get_post_meta($post->ID, '_ksus_sitemap_type', true);
        if (empty($sitemap_type)) {
            $sitemap_type = 'standard';
        }
        ?>
        <div class="ksus-meta-box">
            <p>
                <label for="ksus_sitemap_type"><strong>サイトマップ設定</strong></label>
            </p>
            <select name="ksus_sitemap_type" id="ksus_sitemap_type" style="width: 100%;">
                <option value="exclude" <?php selected($sitemap_type, 'exclude'); ?>>サイトマップに含めない</option>
                <option value="standard" <?php selected($sitemap_type, 'standard'); ?>>通常のサイトマップに含める</option>
            </select>

            <p class="description" style="margin-top: 10px;">
                通常のサイトマップを選択すると、投稿タイプ別サイトマップに含まれます。記事中の画像・動画も自動的に含まれます。<br>
                ニュースサイトマップの設定は、プラグイン管理画面で投稿タイプごとに設定できます。
            </p>
        </div>
        <?php
    }

    /**
     * メタボックスの保存処理
     */
    public function save_meta_box($post_id) {
        // ノンスチェック
        if (!isset($_POST['ksus_meta_box_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['ksus_meta_box_nonce'], 'ksus_save_meta_box')) {
            return;
        }

        // 自動保存の場合は処理しない
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // データの保存
        if (isset($_POST['ksus_sitemap_type'])) {
            $sitemap_type = sanitize_text_field($_POST['ksus_sitemap_type']);
            update_post_meta($post_id, '_ksus_sitemap_type', $sitemap_type);
        }

        // サイトマップを再生成
        do_action('ksus_regenerate_sitemaps');
    }
}
