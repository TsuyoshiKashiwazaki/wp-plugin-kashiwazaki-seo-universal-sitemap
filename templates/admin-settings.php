<?php
/**
 * 管理画面テンプレート
 *
 * @package Kashiwazaki SEO Universal Sitemap
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = KSUS_Admin::get_sitemap_stats();
$urls = KSUS_Admin::get_sitemap_urls();
?>

<div class="wrap">
    <h1>Kashiwazaki SEO Universal Sitemap</h1>

    <?php settings_errors('ksus_messages'); ?>

    <!-- タブナビゲーション -->
    <h2 class="nav-tab-wrapper">
        <a href="#tab-main" class="nav-tab nav-tab-active" data-tab="main">サイトマップ &amp; 設定</a>
        <a href="#tab-stats" class="nav-tab" data-tab="stats">統計情報</a>
        <a href="#tab-usage" class="nav-tab" data-tab="usage">使い方</a>
    </h2>

    <div class="ksus-admin-container">
        <!-- メインタブ -->
        <div id="tab-main" class="ksus-tab-content" style="display: block;">
        <!-- サイトマップURL -->
        <div class="ksus-card">
            <h2>サイトマップURL</h2>
            <p style="margin-top: 0; color: #666;">生成されたサイトマップファイルのURLと状態</p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>サイトマップタイプ</th>
                        <th>URL</th>
                        <th>状態</th>
                        <th>アクション</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $upload_dir = wp_upload_dir();
                    $sitemap_dir = $upload_dir['basedir'] . '/sitemaps/';
                    $is_dynamic_mode = get_option('ksus_generation_mode', 'static') === 'dynamic';

                    // インデックス（動的モードまたはファイル存在）
                    $index_exists = $is_dynamic_mode || file_exists($sitemap_dir . 'sitemap.xml.gz') || file_exists($sitemap_dir . 'sitemap.xml');
                    $is_gzip_enabled_index = get_option('ksus_enable_gzip', false);
                    $index_ext = ($is_gzip_enabled_index && !$is_dynamic_mode) ? '.xml.gz' : '.xml';
                    $index_url = home_url('/sitemap' . $index_ext);
                    ?>
                    <tr>
                        <td><strong>インデックス</strong></td>
                        <td>
                            <?php if ($index_exists): ?>
                                <code style="font-size: 12px;"><?php echo esc_html($index_url); ?></code>
                                <button type="button" class="ksus-copy-url" data-url="<?php echo esc_attr($index_url); ?>" style="border: none; background: none; cursor: pointer; font-size: 12px; padding: 0; margin-left: 5px; vertical-align: middle; opacity: 0.6;" title="コピー">📋</button>
                            <?php else: ?>
                                <code style="font-size: 12px; color: #999;">-</code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_dynamic_mode): ?>
                                <span style="color: #2271b1;">⚡ 動的生成</span>
                            <?php elseif ($index_exists):
                                // 実際に存在するファイル名を取得
                                $index_filename = file_exists($sitemap_dir . 'sitemap.xml.gz') ? 'sitemap.xml.gz' : 'sitemap.xml';
                                $count = KSUS_Admin::get_sitemap_url_count($index_filename);
                            ?>
                                <span style="color: #46b450;">✓ 生成済み (<?php echo number_format($count); ?>件)</span>
                            <?php else: ?>
                                <span style="color: #dc3232;">✗ 未生成</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($index_exists): ?>
                                <a href="<?php echo esc_url($index_url); ?>" target="_blank" class="button button-small">表示</a>
                            <?php else: ?>
                                <span style="color: #999;">表示不可</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                    $is_gzip_enabled = get_option('ksus_enable_gzip', false);
                    $ext = ($is_gzip_enabled && !$is_dynamic_mode) ? '.xml.gz' : '.xml';
                    ?>
                    <?php foreach ($stats as $post_type => $stat):
                        $enabled_post_types = get_option('ksus_enabled_post_types', false);
                        if ($enabled_post_types === false) {
                            $post_types_list = get_post_types(array('public' => true), 'names');
                            unset($post_types_list['attachment']);
                            $enabled_post_types = $post_types_list;
                        }
                        $is_enabled = in_array($post_type, $enabled_post_types);

                        // 分割ファイル情報を取得
                        $split_stats = KSUS_Admin::get_split_sitemap_stats($post_type);
                        $has_files = $split_stats['file_count'] > 0;
                        $post_type_url = home_url('/sitemap-' . $post_type . $ext);
                        $is_available = $is_dynamic_mode ? ($is_enabled && $stat['total'] > 0) : $has_files;
                    ?>
                    <tr>
                        <td><?php echo esc_html($stat['label']); ?></td>
                        <td>
                            <?php if ($is_available): ?>
                                <code style="font-size: 12px;"><?php echo esc_html($post_type_url); ?></code>
                                <button type="button" class="ksus-copy-url" data-url="<?php echo esc_attr($post_type_url); ?>" style="border: none; background: none; cursor: pointer; font-size: 12px; padding: 0; margin-left: 5px; vertical-align: middle; opacity: 0.6;" title="コピー">📋</button>
                                <?php if (!$is_dynamic_mode && $split_stats['file_count'] > 1 && isset($urls[$post_type]['files'])): ?>
                                    <br><small style="color: #666; margin-left: 5px;">
                                        <?php
                                        foreach ($split_stats['files'] as $index => $file_info):
                                            if ($index > 0) echo ', ';
                                            if (isset($urls[$post_type]['files'][$index])) {
                                                echo '<a href="' . esc_url($urls[$post_type]['files'][$index]) . '" target="_blank" style="text-decoration: none;" title="' . esc_attr($file_info['filename']) . '">#' . ($index + 1) . ' (' . number_format($file_info['url_count']) . '件)</a>';
                                            }
                                        endforeach;
                                        ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <code style="font-size: 12px; color: #999;">-</code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_dynamic_mode && $is_enabled && $stat['total'] > 0): ?>
                                <span style="color: #2271b1;">⚡ 動的生成</span>
                            <?php elseif ($has_files): ?>
                                <span style="color: #46b450;">✓ 生成済み (<?php echo number_format($split_stats['total_urls']); ?>件<?php if ($split_stats['file_count'] > 1): echo ' / ' . $split_stats['file_count'] . 'ファイル'; endif; ?>)</span>
                            <?php elseif (!$is_enabled): ?>
                                <span style="color: #999;">無効</span>
                            <?php else: ?>
                                <span style="color: #999;">対象が見つかりません</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_available): ?>
                                <a href="<?php echo esc_url($post_type_url); ?>" target="_blank" class="button button-small">表示</a>
                            <?php else: ?>
                                <span style="color: #999;">表示不可</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Google News 特別枠 -->
                    <tr style="background: #f9f9f9;">
                        <td colspan="4" style="padding: 8px 10px; border-top: 2px solid #0073aa; border-bottom: 1px solid #ddd;">
                            <strong style="color: #0073aa; font-size: 13px;">📰 Googleニュースサイトマップ</strong>
                            <small style="color: #666; margin-left: 10px;">（Google News専用）</small>
                        </td>
                    </tr>
                    <?php
                    $news_post_types = get_option('ksus_news_post_types', array());

                    // 分割ファイル情報を取得
                    $news_split_stats = KSUS_Admin::get_split_sitemap_stats('googlenews');
                    $news_has_files = $news_split_stats['file_count'] > 0;
                    $news_url = home_url('/sitemap-googlenews' . $ext);
                    $news_is_available = $is_dynamic_mode ? !empty($news_post_types) : $news_has_files;
                    ?>
                    <tr style="background: #f0f8ff;">
                        <td style="padding-left: 20px;"><strong>Google News</strong></td>
                        <td>
                            <?php if ($news_is_available): ?>
                                <code style="font-size: 12px;"><?php echo esc_html($news_url); ?></code>
                                <button type="button" class="ksus-copy-url" data-url="<?php echo esc_attr($news_url); ?>" style="border: none; background: none; cursor: pointer; font-size: 12px; padding: 0; margin-left: 5px; vertical-align: middle; opacity: 0.6;" title="コピー">📋</button>
                                <?php if (!$is_dynamic_mode && $news_split_stats['file_count'] > 1 && isset($urls['googlenews']['files'])): ?>
                                    <br><small style="color: #666; margin-left: 5px;">
                                        <?php
                                        foreach ($news_split_stats['files'] as $index => $file_info):
                                            if ($index > 0) echo ', ';
                                            if (isset($urls['googlenews']['files'][$index])) {
                                                echo '<a href="' . esc_url($urls['googlenews']['files'][$index]) . '" target="_blank" style="text-decoration: none;" title="' . esc_attr($file_info['filename']) . '">#' . ($index + 1) . ' (' . number_format($file_info['url_count']) . '件)</a>';
                                            }
                                        endforeach;
                                        ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <code style="font-size: 12px; color: #999;">-</code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_dynamic_mode && !empty($news_post_types)): ?>
                                <span style="color: #2271b1;">⚡ 動的生成</span>
                            <?php elseif ($news_has_files): ?>
                                <span style="color: #46b450;">✓ 生成済み (<?php echo number_format($news_split_stats['total_urls']); ?>件<?php if ($news_split_stats['file_count'] > 1): echo ' / ' . $news_split_stats['file_count'] . 'ファイル'; endif; ?>)</span>
                            <?php elseif (empty($news_post_types)): ?>
                                <span style="color: #999;">無効</span>
                            <?php else: ?>
                                <span style="color: #999;">対象が見つかりません</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($news_is_available): ?>
                                <a href="<?php echo esc_url($news_url); ?>" target="_blank" class="button button-small">表示</a>
                            <?php else: ?>
                                <span style="color: #999;">表示不可</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div style="margin-top: 15px;">
                <?php if (!$is_dynamic_mode): ?>
                <button type="button" id="ksus-regenerate-btn" class="button button-secondary">
                    <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> サイトマップを再生成
                </button>
                <div id="ksus-regenerate-message" style="margin-top: 10px;"></div>
                <?php else: ?>
                <p style="color: #666; margin: 0;"><em>動的生成モードでは再生成ボタンは不要です。サイトマップはリクエスト時に自動生成されます。</em></p>
                <?php endif; ?>
            </div>
        </div>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

        <!-- サイトマップ設定 -->
        <form method="post" action="options.php">
            <?php settings_fields('ksus_settings'); ?>

            <div class="ksus-card">
                <h2>サイトマップ設定</h2>
                <p style="margin-top: 0; color: #666;">サイトマップに含める投稿タイプや画像・動画の設定。設定変更時に自動的に再生成されます。</p>

                <h3>通常サイトマップ - 投稿タイプ</h3>
                <input type="hidden" name="ksus_enabled_post_types[]" value="">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 20%;">投稿タイプ</th>
                            <th style="width: 25%;">サイトマップに含める</th>
                            <th style="width: 25%;">更新頻度</th>
                            <th style="width: 30%;">優先度</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $post_types = get_post_types(array('public' => true), 'names');
                        unset($post_types['attachment']);
                        $enabled_post_types = get_option('ksus_enabled_post_types', false);
                        if ($enabled_post_types === false) {
                            $enabled_post_types = $post_types; // 初回のみデフォルトで全て有効
                        }
                        $post_type_settings = get_option('ksus_post_type_settings', array());

                        foreach ($post_types as $post_type):
                            $post_type_obj = get_post_type_object($post_type);
                            $checked = in_array($post_type, $enabled_post_types);

                            // デフォルト値
                            $default_changefreq = ($post_type === 'post') ? 'weekly' : 'monthly';
                            $default_priority = ($post_type === 'post') ? 0.8 : (($post_type === 'page') ? 0.6 : 0.5);

                            $changefreq = isset($post_type_settings[$post_type]['changefreq'])
                                ? $post_type_settings[$post_type]['changefreq']
                                : $default_changefreq;
                            $priority = isset($post_type_settings[$post_type]['priority'])
                                ? $post_type_settings[$post_type]['priority']
                                : $default_priority;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($post_type_obj->labels->name); ?></strong></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="ksus_enabled_post_types[]" value="<?php echo esc_attr($post_type); ?>" <?php checked($checked); ?>>
                                    生成する
                                </label>
                            </td>
                            <td>
                                <select name="ksus_post_type_settings[<?php echo esc_attr($post_type); ?>][changefreq]">
                                    <option value="always" <?php selected($changefreq, 'always'); ?>>常に (always)</option>
                                    <option value="hourly" <?php selected($changefreq, 'hourly'); ?>>毎時 (hourly)</option>
                                    <option value="daily" <?php selected($changefreq, 'daily'); ?>>毎日 (daily)</option>
                                    <option value="weekly" <?php selected($changefreq, 'weekly'); ?>>毎週 (weekly)</option>
                                    <option value="monthly" <?php selected($changefreq, 'monthly'); ?>>毎月 (monthly)</option>
                                    <option value="yearly" <?php selected($changefreq, 'yearly'); ?>>毎年 (yearly)</option>
                                    <option value="never" <?php selected($changefreq, 'never'); ?>>更新なし (never)</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="ksus_post_type_settings[<?php echo esc_attr($post_type); ?>][priority]"
                                       value="<?php echo esc_attr($priority); ?>"
                                       min="0" max="1" step="0.1" style="width: 80px;">
                                <small style="color: #666;">0.0〜1.0</small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3 style="margin-top: 30px;">通常サイトマップ - 生成設定</h3>
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong>生成モード</strong></td>
                            <td>
                                <?php $generation_mode = get_option('ksus_generation_mode', 'static'); ?>
                                <label style="margin-right: 20px;">
                                    <input type="radio" name="ksus_generation_mode" value="static" <?php checked($generation_mode, 'static'); ?>>
                                    静的生成（ファイル保存）
                                </label>
                                <label>
                                    <input type="radio" name="ksus_generation_mode" value="dynamic" <?php checked($generation_mode, 'dynamic'); ?>>
                                    動的生成（リクエスト時に生成）
                                </label>
                                <p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">
                                    <strong>静的生成：</strong> ファイルに保存。高速だが、投稿変更時に再生成が必要。<br>
                                    <strong>動的生成：</strong> 常に最新の状態を返す。再生成不要だが、大量の投稿がある場合は負荷が高い。
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>画像情報</strong></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="ksus_include_images" value="1" <?php checked(get_option('ksus_include_images', true)); ?>>
                                    サイトマップに画像情報を含める
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>動画情報</strong></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="ksus_include_videos" value="1" <?php checked(get_option('ksus_include_videos', true)); ?>>
                                    サイトマップに動画情報を含める
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>GZIP圧縮</strong></td>
                            <td>
                                <?php if ($generation_mode === 'dynamic'): ?>
                                <label style="color: #999;">
                                    <input type="checkbox" name="ksus_enable_gzip" value="1" disabled>
                                    サイトマップファイルをGZIP圧縮する (.xml.gz)
                                </label>
                                <p style="margin: 5px 0 0 25px; color: #999; font-size: 12px;">
                                    動的生成モードではGZIP圧縮は使用できません。
                                </p>
                                <?php else: ?>
                                <label>
                                    <input type="checkbox" name="ksus_enable_gzip" value="1" <?php checked(get_option('ksus_enable_gzip', false)); ?>>
                                    サイトマップファイルをGZIP圧縮する (.xml.gz)
                                </label>
                                <p style="margin: 5px 0 0 25px; color: #666; font-size: 12px;">
                                    ファイルサイズを大幅に削減できます（推奨）。Googlebot対応。
                                </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>HTMLヘッダー出力</strong></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="ksus_enable_head_link" value="1" <?php checked(get_option('ksus_enable_head_link', true)); ?>>
                                    &lt;head&gt;に&lt;link rel="sitemap"&gt;を追加
                                </label>
                                <p style="margin: 5px 0 0 25px; color: #666; font-size: 12px;">
                                    検索エンジンがサイトマップを発見しやすくなります（推奨）。
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php
                $news_post_types = get_option('ksus_news_post_types', array());
                $has_news_enabled = !empty($news_post_types);
                $collapsed_class = $has_news_enabled ? '' : 'collapsed';
                $content_style = $has_news_enabled ? '' : 'style="display: none;"';
                ?>
                <h3 style="margin-top: 30px; cursor: pointer; user-select: none;" class="ksus-collapsible-toggle <?php echo $collapsed_class; ?>">
                    <span class="dashicons dashicons-arrow-down-alt2" style="margin-top: 2px;"></span>
                    ニュースサイトマップ - 投稿タイプ
                    <?php if (!$has_news_enabled): ?>
                        <small style="color: #999; font-weight: normal; margin-left: 10px;">（クリックして設定）</small>
                    <?php endif; ?>
                </h3>
                <div class="ksus-collapsible-content" <?php echo $content_style; ?>>
                <p style="margin-top: 0; color: #666;">Googleニュースに掲載する投稿タイプを選択</p>
                <input type="hidden" name="ksus_news_post_types[]" value="">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>投稿タイプ</th>
                            <th>ニュースサイトマップに含める</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $post_types = get_post_types(array('public' => true), 'names');
                        unset($post_types['attachment']);
                        $news_post_types = get_option('ksus_news_post_types', array());
                        foreach ($post_types as $post_type):
                            $post_type_obj = get_post_type_object($post_type);
                            $checked = in_array($post_type, $news_post_types);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($post_type_obj->labels->name); ?></strong></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="ksus_news_post_types[]" value="<?php echo esc_attr($post_type); ?>" <?php checked($checked); ?>>
                                    この投稿タイプをニュースサイトマップに含める
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div><!-- .ksus-collapsible-content -->

                <?php submit_button('設定を保存'); ?>
            </div>
        </form>
        </div><!-- #tab-main -->

        <!-- 統計情報タブ -->
        <div id="tab-stats" class="ksus-tab-content" style="display: none;">
        <div class="ksus-card">
            <h2>サイトマップ統計</h2>
            <p style="margin-top: 0; color: #666;">各投稿タイプのサイトマップ掲載状況</p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>投稿タイプ</th>
                        <th>合計</th>
                        <th>通常</th>
                        <th>画像</th>
                        <th>動画</th>
                        <th>除外</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $post_type => $stat): ?>
                    <tr>
                        <td><strong><?php echo esc_html($stat['label']); ?></strong></td>
                        <td><?php echo esc_html($stat['total']); ?></td>
                        <td><?php echo esc_html($stat['standard']); ?></td>
                        <td><?php echo esc_html($stat['image']); ?></td>
                        <td><?php echo esc_html($stat['video']); ?></td>
                        <td><?php echo esc_html($stat['exclude']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div><!-- #tab-stats -->

        <!-- 使い方タブ -->
        <div id="tab-usage" class="ksus-tab-content" style="display: none;">
        <div class="ksus-card">
            <h2>📚 詳細な使い方ガイド</h2>

            <h3>🎯 基本的な使い方</h3>
            <ol>
                <li><strong>プラグインのインストールと有効化</strong>
                    <p>プラグインを有効化すると、自動的にサイトマップが生成されます。</p>
                </li>

                <li><strong>Google Search Consoleに登録</strong>
                    <p>インデックスサイトマップURL：<code><?php echo esc_html($urls['index']['url']); ?></code></p>
                    <p>このURLをGoogle Search Consoleの「サイトマップ」セクションに登録してください。</p>
                </li>

                <li><strong>サイトマップの確認</strong>
                    <p>「サイトマップ & 設定」タブのサイトマップURLテーブルから、各サイトマップにアクセスして内容を確認できます。</p>
                </li>
            </ol>

            <h3>⚙️ 詳細設定</h3>

            <h4>投稿タイプの設定</h4>
            <ul>
                <li><strong>サイトマップに含める：</strong> チェックを入れた投稿タイプのサイトマップが生成されます</li>
                <li><strong>更新頻度（changefreq）：</strong> Googlebotにコンテンツの更新頻度を伝えます
                    <ul>
                        <li>always：常に更新</li>
                        <li>hourly：毎時</li>
                        <li>daily：毎日（ブログ推奨）</li>
                        <li>weekly：毎週（通常の投稿推奨）</li>
                        <li>monthly：毎月（固定ページ推奨）</li>
                        <li>yearly：毎年</li>
                        <li>never：更新なし</li>
                    </ul>
                </li>
                <li><strong>優先度（priority）：</strong> 0.0〜1.0の値で、サイト内での重要度を指定
                    <ul>
                        <li>1.0：最重要ページ</li>
                        <li>0.8：重要なページ（投稿推奨）</li>
                        <li>0.6：標準（固定ページ推奨）</li>
                        <li>0.5：通常のページ</li>
                        <li>0.0〜0.4：優先度の低いページ</li>
                    </ul>
                </li>
            </ul>

            <h4>画像・動画情報</h4>
            <ul>
                <li><strong>画像情報：</strong> アイキャッチ画像と本文中の画像をサイトマップに含めます（Google画像検索対策）</li>
                <li><strong>動画情報：</strong> YouTube・Vimeo動画をサイトマップに含めます（Google動画検索対策）</li>
                <li><strong>GZIP圧縮：</strong> ファイルサイズを約90-98%削減。帯域幅の節約に効果的（推奨）</li>
                <li><strong>HTMLヘッダー出力：</strong> &lt;head&gt;に&lt;link rel="sitemap"&gt;を追加。検索エンジンの発見を容易にします</li>
            </ul>

            <h4>ニュースサイトマップ</h4>
            <ul>
                <li>Googleニュースに掲載したい投稿タイプにチェックを入れてください</li>
                <li>自動的に1,000件ごとに分割されます（Google仕様）</li>
                <li>最新記事が優先的に掲載されます</li>
            </ul>

            <h3>🚀 高度な機能</h3>

            <h4>自動分割機能</h4>
            <ul>
                <li><strong>投稿タイプサイトマップ：</strong> 50,000件を超えると自動的に複数ファイルに分割
                    <ul>
                        <li>sitemap-post.xml（最初の50,000件）</li>
                        <li>sitemap-post-2.xml（次の50,000件）</li>
                        <li>sitemap-post-3.xml（さらに次の50,000件）...</li>
                    </ul>
                </li>
                <li><strong>ニュースサイトマップ：</strong> 1,000件を超えると自動分割
                    <ul>
                        <li>sitemap-googlenews.xml（最初の1,000件）</li>
                        <li>sitemap-googlenews-2.xml（次の1,000件）...</li>
                    </ul>
                </li>
                <li>すべての分割ファイルは自動的にインデックスサイトマップ（sitemap.xml）に登録されます</li>
            </ul>

            <h4>個別投稿の除外</h4>
            <p>投稿編集画面の右サイドバーにある「Kashiwazaki SEO Universal Sitemap」メタボックスから、特定の投稿をサイトマップから除外できます。</p>
            <ul>
                <li>非公開にしたい投稿</li>
                <li>下書き段階の投稿</li>
                <li>SEO評価を受けたくないページ</li>
            </ul>

            <h3>💡 トラブルシューティング</h3>

            <h4>サイトマップが表示されない場合</h4>
            <ol>
                <li><strong>リライトルールをフラッシュ：</strong>
                    <p>「設定」→「パーマリンク設定」を開き、「変更を保存」をクリック</p>
                </li>
                <li><strong>手動で再生成：</strong>
                    <p>「サイトマップを再生成」ボタンをクリック</p>
                </li>
                <li><strong>ファイル権限確認：</strong>
                    <p>/wp-content/uploads/sitemaps/ ディレクトリが書き込み可能か確認</p>
                </li>
            </ol>

            <h4>設定が反映されない場合</h4>
            <ul>
                <li>設定保存後、必ず「サイトマップを再生成」ボタンをクリック</li>
                <li>ブラウザのキャッシュをクリア</li>
                <li>debug.log（/wp-content/debug.log）でエラーを確認</li>
            </ul>

            <h3>📖 参考情報</h3>
            <ul>
                <li><strong>Googleサイトマップ仕様：</strong> <a href="https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview" target="_blank">公式ドキュメント</a></li>
                <li><strong>Googleニュースサイトマップ：</strong> <a href="https://developers.google.com/search/docs/crawling-indexing/sitemaps/news-sitemap" target="_blank">公式ドキュメント</a></li>
                <li><strong>画像サイトマップ：</strong> <a href="https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps" target="_blank">公式ドキュメント</a></li>
                <li><strong>動画サイトマップ：</strong> <a href="https://developers.google.com/search/docs/crawling-indexing/sitemaps/video-sitemaps" target="_blank">公式ドキュメント</a></li>
            </ul>

            <div style="padding: 15px; background: #f0f0f1; border-left: 4px solid #0073aa; margin-top: 20px;">
                <h4 style="margin-top: 0;">⚡ 自動再生成について</h4>
                <p>サイトマップは以下のタイミングで自動的に再生成されます：</p>
                <ul style="margin-bottom: 0;">
                    <li>設定保存時</li>
                    <li>投稿の公開・更新時</li>
                    <li>投稿の削除時</li>
                </ul>
            </div>
        </div>
        </div><!-- #tab-usage -->

    </div><!-- .ksus-admin-container -->

    <!-- プラグイン情報 -->
    <div class="ksus-card ksus-info" style="margin-top: 20px;">
        <p>
            <strong>Kashiwazaki SEO Universal Sitemap</strong> Version <?php echo esc_html(KSUS_VERSION); ?><br>
            Author: 柏崎剛 (Tsuyoshi Kashiwazaki) |
            <a href="https://www.tsuyoshikashiwazaki.jp/profile/" target="_blank">プロフィール</a> |
            <a href="https://www.tsuyoshikashiwazaki.jp" target="_blank">ウェブサイト</a>
        </p>
    </div>
</div>

<style>
.ksus-collapsible-content {
    overflow: hidden;
    transition: max-height 0.3s ease-out;
}
.ksus-collapsible-toggle .dashicons {
    transition: transform 0.3s ease;
}
.ksus-collapsible-toggle.collapsed .dashicons {
    transform: rotate(-90deg);
}
</style>

<script>
jQuery(document).ready(function($) {
    // タブ切り替え
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tab = $(this).data('tab');

        // すべてのタブを非アクティブ化
        $('.nav-tab').removeClass('nav-tab-active');
        $('.ksus-tab-content').hide();

        // クリックされたタブをアクティブ化
        $(this).addClass('nav-tab-active');
        $('#tab-' + tab).show();

        // URLハッシュを更新
        window.location.hash = 'tab-' + tab;
    });

    // ページロード時のハッシュ処理
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        const tab = hash.replace('tab-', '');
        if ($('#' + hash).length > 0) {
            $('.nav-tab').removeClass('nav-tab-active');
            $('.ksus-tab-content').hide();
            $('[data-tab="' + tab + '"]').addClass('nav-tab-active');
            $('#' + hash).show();
        }
    }

    // 折りたたみ機能
    $('.ksus-collapsible-toggle').on('click', function() {
        const $content = $(this).next('.ksus-collapsible-content');
        const $icon = $(this).find('.dashicons');

        if ($content.is(':visible')) {
            $content.slideUp(300);
            $(this).addClass('collapsed');
        } else {
            $content.slideDown(300);
            $(this).removeClass('collapsed');
        }
    });
});
</script>
