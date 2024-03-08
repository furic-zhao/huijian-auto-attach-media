<?php

/**
 * Plugin Name: Huijian Auto Attach Orphaned Media
 * Description: Automatically attach orphaned media files to the posts containing them, with an option to manually attach them.
 * Version:           1.4.0
 * Requires PHP:      7.4
 * Author:            慧见
 * License:           MIT
 */

class HuijianAutoAttachMedia
{
    function __construct()
    {
        add_action('admin_menu', [$this, 'register_media_attach_page'], 10, 0);
        add_action('wp_ajax_manual_attach_media', [$this, 'manual_attach_media'], 10, 0); // 处理手动附加操作
    }

    public function register_media_attach_page()
    {
        add_options_page('Auto Attach Media', 'Auto Attach Media', 'manage_options', 'auto-attach-media', [$this, 'media_attach_page']);
    }

    public function media_attach_page()
    {
        require_once(plugin_dir_path(__FILE__)  . 'include/wp-list-table.php');
        $ListTable = new Huijian_Attach_Media_List_Table();
        $ListTable->prepare_items();

        if (isset($_POST['action']) && $_POST['action'] === 'attach' && check_admin_referer('attach-media')) {

            $ids = empty($_POST['ID']) ? array() : $_POST['ID'];
            
            foreach ($ids as $id) {
                $id_arr = explode('|', $id);
            }
        }
?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Attach Media</h1>
            <?php wp_nonce_field('auto_attach_media_nonce', 'auto_attach_media_nonce_field'); ?>
            <hr class="wp-header-end">
            <form method="get">
                <input type="hidden" name="page" value="auto-attach-media" />
                <?php echo $ListTable->search_box('search', 'search_id'); ?>
            </form>
            <form method="post">
                <?php
                $ListTable->display();
                ?>
                <?php
                //check_admin_referer('cje_cource_fee');
                wp_nonce_field('attach-media');
                ?>

            </form>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.attach-media-button').click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var nonce = $('#auto_attach_media_nonce_field').val();
                    var mediaId = button.data('media-id');
                    var postId = button.data('post-id');

                    $.post(ajaxurl, {
                        'action': 'manual_attach_media',
                        'security': nonce,
                        'media_id': mediaId,
                        'post_id': postId
                    }).done(function(response) {
                        button.after('Media attached successfully!');
                        button.remove(); // 移除按钮
                    }).fail(function() {
                        button.after('Failed to attach media.');
                    });
                });
            });
        </script>
<?php
    }

    public function manual_attach_media()
    {
        if (!current_user_can('manage_options')) {
            echo json_encode(['message' => 'Permission Denied']);
            wp_die();
        }
        check_ajax_referer('auto_attach_media_nonce', 'security');
        $media_id = isset($_POST['media_id']) ? intval($_POST['media_id']) : 0;
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        wp_update_post([
            'ID' => $media_id,
            'post_parent' => $post_id,
        ]);

        echo json_encode(['message' => 'Media attached successfully.']);
        wp_die();
    }
}

new HuijianAutoAttachMedia();
