<?php

/**
 * Plugin Name: Huijian Auto Attach Orphaned Media
 * Description: Automatically attach orphaned media files to the posts containing them.
 * Version:           1.2.0
 * Requires PHP:      7.4
 * Author:            慧见
 * License:           MIT
 */

class HuijianAutoAttachMedia
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
                <div>Progress: <span id="progress_percentage">0%</span></div>
                <div id="progress_bar" style="width: 0%; background-color: green; height: 20px;"></div>
            </div>
            <script type="text/javascript">
                var totalMedia = <?php echo $this->get_total_orphaned_media_count(); ?>;
                jQuery(document).ready(function($) {
                    var offset = 0;
                    var is_processing = false;

                    function process_batch() {
                        if (is_processing) return;

                        is_processing = true;
                        var nonce = $('#auto_attach_media_nonce_field').val();
                        $('#progress').append('<div>Processing batch starting from ' + offset + '...</div>');

                        $.post(ajaxurl, {
                            'action': 'start_attach_media',
                            'security': nonce,
                            'offset': offset
                        }).done(function(data) {
                            try {
                                var result = JSON.parse(data);
                                totalMedia = result.remaining; // 更新剩余孤立媒体的总数
                                $('#progress').append('<div>' + result.message + '</div>');
                                if (result.finished) {
                                    $('#progress').append('<div>All media processed successfully.</div>');
                                    $('#progress_bar').css('width', '100%');
                                    $('#progress_percentage').text('100%');
                                } else {
                                    offset = result.new_offset;
                                    var progressPercentage = ((offset / totalMedia) * 100).toFixed(2);
                                    $('#progress_bar').css('width', progressPercentage + '%');
                                    $('#progress_percentage').text(progressPercentage + '%');
                                    is_processing = false;
                                    process_batch();
                                }
                            } catch (e) {
                                $('#progress').append('<div>Error parsing response: ' + e.message + '</div>');
                                is_processing = false;
                            }
                        }).fail(function(xhr, textStatus, errorThrown) {
                            $('#progress').append('<div>AJAX request failed: ' + textStatus + ' - ' + errorThrown + '</div>');
                            is_processing = false;
                        });
                    }

                    $('#start_attach').click(function() {
                        process_batch();
                    });
                });
            </script>

<?php
        }
    }

    public function auto_attach_orphaned_media()
    {
        if (!current_user_can('manage_options')) {
            echo json_encode(['message' => 'Permission Denied']);
            wp_die();
        }
        check_ajax_referer('auto_attach_media_nonce', 'security');
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_parent' => 0,
            'posts_per_page' => 20,
            'offset' => $offset,
        ];
        global $wpdb;
        $orphaned_media = get_posts($args);
        $message = [];

        foreach ($orphaned_media as $media) {
            $file_url = wp_get_attachment_url($media->ID); // 获取媒体文件的完整 URL
            $home_url = home_url(); // 获取 WordPress 站点的主地址

            // 确保两个 URL 都没有结尾的斜杠
            $file_url = untrailingslashit($file_url);
            $home_url = untrailingslashit($home_url);

            $relative_path = substr($file_url, strlen($home_url));

            $message[] = 'Processing: ' . $relative_path;

            // 直接在数据库中搜索文章和页面的内容
            $sql = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s AND post_status = 'publish'", '%' . $wpdb->esc_like($relative_path) . '%');
            $post_ids = $wpdb->get_col($sql);

            foreach ($post_ids as $post_id) {
                wp_update_post([
                    'ID' => $media->ID,
                    'post_parent' => $post_id,
                ]);
            }
        }

        $finished = ($offset + 20) >= $this->get_total_orphaned_media_count();
        echo json_encode([
            'message' => implode('<br>', $message),
            'new_offset' => $finished ? null : $offset + 20,
            'finished' => $finished,
            'remaining' => $this->get_total_orphaned_media_count() // 添加这行以返回剩余孤立媒体的总数
        ]);
        wp_die();
    }

    private function get_total_orphaned_media_count()
    {
        $query = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_parent' => 0,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        return $query->found_posts;
    }
}

new HuijianAutoAttachMedia();
