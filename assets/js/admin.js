/**
 * 管理画面JavaScript
 *
 * @package KASHIWAZAKI SEO Universal Sitemap
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * サイトマップ再生成ボタン
         */
        $('#ksus-regenerate-btn').on('click', function() {
            var $button = $(this);
            var $message = $('#ksus-regenerate-message');

            // ボタンを無効化
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spin"></span> 再生成中...');

            // メッセージをクリア
            $message.removeClass('success error').hide().text('');

            // AJAX リクエスト
            $.ajax({
                url: ksusAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ksus_regenerate_sitemaps',
                    nonce: ksusAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // 成功したらページをリロード
                        $message
                            .addClass('success')
                            .text(response.data.message + ' ページを再読み込みします...')
                            .show();

                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        $message
                            .addClass('error')
                            .text(response.data.message || 'エラーが発生しました。')
                            .show();

                        // ボタンを有効化
                        $button.prop('disabled', false);
                        $button.html('<span class="dashicons dashicons-update"></span> サイトマップを再生成');
                    }
                },
                error: function() {
                    $message
                        .addClass('error')
                        .text('通信エラーが発生しました。')
                        .show();

                    // ボタンを有効化
                    $button.prop('disabled', false);
                    $button.html('<span class="dashicons dashicons-update"></span> サイトマップを再生成');
                }
            });
        });

        /**
         * URLコピーボタン
         */
        $('.ksus-copy-url').on('click', function() {
            var $button = $(this);
            var url = $button.data('url');

            // クリップボードにコピー
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    showCopySuccess($button);
                }).catch(function() {
                    fallbackCopyToClipboard(url, $button);
                });
            } else {
                fallbackCopyToClipboard(url, $button);
            }
        });

        /**
         * コピー成功時の表示
         */
        function showCopySuccess($button) {
            var originalHtml = $button.html();
            $button.html('✓');

            setTimeout(function() {
                $button.html(originalHtml);
            }, 1500);
        }

        /**
         * フォールバック：クリップボードにコピー
         */
        function fallbackCopyToClipboard(text, $button) {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                showCopySuccess($button);
            } catch (err) {
                alert('コピーに失敗しました。手動でコピーしてください。');
            }

            $temp.remove();
        }

        /**
         * アニメーション用のCSS追加
         */
        if (!$('style#ksus-spin-animation').length) {
            $('<style id="ksus-spin-animation">')
                .text('@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } } .spin { animation: spin 1s linear infinite; display: inline-block; }')
                .appendTo('head');
        }
    });

})(jQuery);
