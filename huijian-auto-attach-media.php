<?php

/**
 * Plugin Name: Huijian Auto Attach Orphaned Media
 * Description: Automatically attach orphaned media files to the posts containing them.
 */

class HuijianAutoAtachMedia
{
    function __construct()
    {
        add_action('admin_menu', [$this, 'register_media_attach_page'], 10, 0);
        add_action('wp_ajax_start_attach_media', [$this, 'auto_attach_orphaned_media'], 10, 0);
    }

    public function register_media_attach_page()
    {
        add_options_page('Auto Attach Media', 'Auto Attach Media', 'manage_options', 'auto-attach-media', [$this, 'media_attach_page']);
    }

    public function media_attach_page()
    {
        if (current_user_can('manage_options')) {
?>
            <div class="wrap">
                <h2>Auto Attach Orphaned Media</h2>
                <?php wp_nonce_field('auto_attach_media_nonce', 'auto_attach_media_nonce_field'); ?>
                <button id="start_attach">Start Attach</button>
                <div id="progress"></div>
            </div>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var offset = 0; // 开始的偏移量
                    var is_processing = false; // 是否正在处理

                    function process_batch() {
                        if (is_processing) return; // 如果正在处理，则退出

                        is_processing = true;
                        var nonce = $('#auto_attach_media_nonce_field').val();
                        $('#progress').append('<div>Processing batch starting from ' + offset + '...</div>');

                        $.post(ajaxurl, {
                            'action': 'start_attach_media',
                            'security': nonce,
                            'offset': offset // 传递当前偏移量到服务器
                        }).done(function(data) {
                            try {
                                var result = JSON.parse(data);
                                $('#progress').append('<div>' + result.message + '</div>');
                                // ... 其他代码 ...
                            } catch (e) {
                                $('#progress').append('<div>Error parsing response</div>');
                            }
                        }).fail(function() {
                            $('#progress').append('<div>AJAX request failed</div>');
                        });
                    }

                    $('#start_attach').click(function() {
                        process_batch(); // 开始处理第一个批次
                    });
                });
            </script>

<?php
        }
    }

    public function auto_attach_orphaned_media()
    {
        // Check permission and nonce
        if (!current_user_can('manage_options')) {
            echo json_encode(['message' => 'Permission Denied']);
            wp_die();
        }
        check_ajax_referer('auto_attach_media_nonce', 'security');
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        $message = []; // 用于存储输出消息的数组

        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_parent' => 0,
            'posts_per_page' => 20,
            'offset' => $offset,
        );

        $orphaned_media = get_posts($args);

        if (empty($orphaned_media)) {
            echo json_encode(['message' => 'No orphaned media found.']);
            die();
        }

        $message[] = 'Found ' . count($orphaned_media) . ' orphaned media.';

        foreach ($orphaned_media as $media) {
            $file_url = wp_get_attachment_url($media->ID);
            $message[] = 'Processing: ' . $file_url; // 将消息保存到数组中

            $args = array(
                's' => $file_url,
                'post_type' => array('post', 'page'),
            );

            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    wp_update_post(array(
                        'ID' => $media->ID,
                        'post_parent' => get_the_ID(),
                    ));
                }
            }
            wp_reset_postdata();
        }

        // 使用 json_encode 输出
        echo json_encode(['message' => implode('<br>', $message), 'new_offset' => $offset + 20]);
        wp_die();
    }
}
new HuijianAutoAtachMedia();
