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

    <div class="ksus-admin-container">
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

                    // インデックス
                    $index_exists = file_exists($sitemap_dir . 'sitemap.xml');
                    ?>
                    <tr>
                        <td><strong>インデックス</strong></td>
                        <td>
                            <code style="font-size: 12px;"><?php echo esc_html($urls['index']); ?></code>
                            <button type="button" class="ksus-copy-url" data-url="<?php echo esc_attr($urls['index']); ?>" style="border: none; background: none; cursor: pointer; font-size: 12px; padding: 0; margin-left: 5px; vertical-align: middle; opacity: 0.6;" title="コピー">📋</button>
                        </td>
                        <td>
                            <?php if ($index_exists):
                                $count = KSUS_Admin::get_sitemap_url_count('sitemap.xml');
                            ?>
                                <span style="color: #46b450;">✓ 生成済み (<?php echo $count; ?>件)</span>
                            <?php else: ?>
                                <span style="color: #dc3232;">✗ 未生成</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($index_exists): ?>
                                <a href="<?php echo esc_url($urls['index']); ?>" target="_blank" class="button button-small">表示</a>
                            <?php else: ?>
                                <span style="color: #999;">表示不可</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php foreach ($stats as $post_type => $stat):
                        $file_exists = file_exists($sitemap_dir . 'sitemap-' . $post_type . '.xml');
                        $enabled_post_types = get_option('ksus_enabled_post_types', false);
                        if ($enabled_post_types === false) {
                            $post_types_list = get_post_types(array('public' => true), 'names');
                            unset($post_types_list['attachment']);
                            $enabled_post_types = $post_types_list;
                        }
                        $is_enabled = in_array($post_type, $enabled_post_types);
                    ?>
                    <tr>
                        <td><?php echo esc_html($stat['label']); ?></td>
                        <td>
                            <code style="font-size: 12px;"><?php echo esc_html($urls[$post_type]); ?></code>
                            <button type="button" class="ksus-copy-url" data-url="<?php echo esc_attr($urls[$post_type]); ?>" style="border: none; background: none; cursor: pointer; font-size: 12px; padding: 0; margin-left: 5px; vertical-align: middle; opacity: 0.6;" title="コピー">📋</button>
                        </td>
                        <td>
                            <?php if ($file_exists):
                                $count = KSUS_Admin::get_sitemap_url_count('sitemap-' . $post_type . '.xml');
                            ?>
                                <span style="color: #46b450;">✓ 生成済み (<?php echo $count; ?>件)</span>
                            <?php elseif ($is_enabled && $stat['total'] == 0): ?>
                                <span style="color: #999;">対象が見つかりません</span>
                            <?php elseif (!$is_enabled): ?>
                                <span style="color: #999;">無効</span>
                            <?php else: ?>
                                <span style="color: #999;">対象が見つかりません</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($file_exists): ?>
                                <a href="<?php echo esc_url($urls[$post_type]); ?>" target="_blank" class="button button-small">表示</a>
                            <?php else: ?>
                                <span style="color: #999;">表示不可</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php
                    $googlenews_file = $sitemap_dir . 'sitemap-googlenews.xml';
                    $googlenews_exists = file_exists($googlenews_file);
                    $news_post_types = get_option('ksus_news_post_types', array());

                    // デバッグ用（本番環境では削除してください）
                    // error_log("Google News Sitemap Debug - File: {$googlenews_file}, Exists: " . ($googlenews_exists ? 'YES' : 'NO') . ", News Types: " . print_r($news_post_types, true));
                    ?>
                    <tr>
                        <td><strong>Google News</strong></td>
                        <td>
                            <code style="font-size: 12px;"><?php echo esc_html($urls['googlenews']); ?></code>
                            <button type="button" class="ksus-copy-url" data-url="<?php echo esc_attr($urls['googlenews']); ?>" style="border: none; background: none; cursor: pointer; font-size: 12px; padding: 0; margin-left: 5px; vertical-align: middle; opacity: 0.6;" title="コピー">📋</button>
                        </td>
                        <td>
                            <?php if ($googlenews_exists):
                                $count = KSUS_Admin::get_sitemap_url_count('sitemap-googlenews.xml');
                            ?>
                                <span style="color: #46b450;">✓ 生成済み (<?php echo $count; ?>件)</span>
                            <?php elseif (empty($news_post_types)): ?>
                                <span style="color: #999;">無効</span>
                            <?php else: ?>
                                <span style="color: #999;">対象が見つかりません</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($googlenews_exists): ?>
                                <a href="<?php echo esc_url($urls['googlenews']); ?>" target="_blank" class="button button-small">表示</a>
                            <?php else: ?>
                                <span style="color: #999;">表示不可</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div style="margin-top: 15px;">
                <button type="button" id="ksus-regenerate-btn" class="button button-secondary">
                    <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> サイトマップを再生成
                </button>
                <div id="ksus-regenerate-message" style="margin-top: 10px;"></div>
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

                <h3 style="margin-top: 30px;">通常サイトマップ - 画像・動画</h3>
                <table class="widefat striped">
                    <tbody>
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
                    </tbody>
                </table>

                <h3 style="margin-top: 30px;">ニュースサイトマップ - 投稿タイプ</h3>
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

                <?php submit_button('設定を保存'); ?>
            </div>
        </form>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

        <!-- 統計情報 -->
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

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

        <!-- 使い方 -->
        <div class="ksus-card">
            <h2>使い方</h2>
            <ol>
                <li><strong>サイトマップ設定</strong><br>
                    「サイトマップ設定」で投稿タイプの有効/無効、画像・動画、ニュースサイトマップの設定を行います。</li>
                <li><strong>個別投稿の除外設定</strong><br>
                    各投稿の編集画面サイドバーにある「Kashiwazaki SEO Universal Sitemap」メタボックスから、個別にサイトマップから除外できます。</li>
                <li><strong>Google Search Consoleに登録</strong><br>
                    インデックスサイトマップURL（<code><?php echo esc_html($urls['index']); ?></code>）をGoogle Search Consoleに登録してください。</li>
            </ol>
            <p style="padding: 10px; background: #f0f0f1; border-left: 3px solid #666;">
                <strong>自動再生成について</strong><br>
                サイトマップは設定保存時、および投稿の公開・更新時に自動的に再生成されます。
            </p>
        </div>

        <!-- プラグイン情報 -->
        <div class="ksus-card ksus-info">
            <p>
                <strong>Kashiwazaki SEO Universal Sitemap</strong> Version <?php echo esc_html(KSUS_VERSION); ?><br>
                Author: 柏崎剛 (Tsuyoshi Kashiwazaki) |
                <a href="https://www.tsuyoshikashiwazaki.jp/profile/" target="_blank">プロフィール</a> |
                <a href="https://www.tsuyoshikashiwazaki.jp" target="_blank">ウェブサイト</a>
            </p>
        </div>
    </div>
</div>
