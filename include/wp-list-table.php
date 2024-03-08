<?php

// Loading table class
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Huijian_Attach_Media_List_Table extends WP_List_Table
{
    /** 表格总数 */
    private $total_items;

    /**
     * 获取表格数据
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public function get_table_data($per_page = 5, $page_number = 1)
    {
        $search = !empty($_GET['s']) ? trim($_GET['s']) : '';
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'post_date';
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'desc';

        global $wpdb;

        $where = '1=1';

        if (!empty($search)) {
            $where .= " AND post_title Like '%{$search}%'";
        }
        $where .= " AND post_parent = 0 AND post_type = 'attachment' AND post_status = 'inherit'";

        $table = $wpdb->prefix . 'posts';

        $sql = "SELECT COUNT(*) FROM $table WHERE $where";

        $this->total_items = $wpdb->get_var($sql); // Get total count of items

        // Modify the query to retrieve actual data
        $sql = "SELECT ID, post_title FROM $table WHERE $where";

        if (!empty($orderby)) {
            $sql .= " ORDER BY $orderby $order";
        }

        $offset = ($page_number - 1) * $per_page;
        $sql .= " LIMIT $per_page OFFSET $offset";

        $result = $wpdb->get_results($sql, 'ARRAY_A');

        // 检查每个项是否应显示复选框
        foreach ($result as &$item) {
            $file_url = wp_get_attachment_url($item['ID']);
            $posts = $this->find_post_by_media($file_url);
            $item['file_url'] = $file_url;
            $item['posts'] = empty($posts) ? array() : $posts;
            $item['show_checkbox'] = !empty($posts); // 如果找到相关的帖子，则设置为true
        }

        return $result;
    }

    /**
     * 根据文件的URL查找包含的文章
     */
    private function find_post_by_media($file_url)
    {
        $home_url = home_url(); // 获取 WordPress 站点的主地址

        // 确保两个 URL 都没有结尾的斜杠
        $file_url = untrailingslashit($file_url);
        $home_url = untrailingslashit($home_url);

        $file_url = substr($file_url, strlen($home_url));

        global $wpdb;
        $sql = $wpdb->prepare("SELECT ID,post_title FROM $wpdb->posts WHERE post_content LIKE %s AND post_status = 'publish'", '%' . $wpdb->esc_like($file_url) . '%');
        $post = $wpdb->get_row($sql, 'ARRAY_A');
        return $post;
    }

    /**
     * 为批量操作添加复选框
     */
    function column_cb($item)
    {
        if (!$item['show_checkbox']) {
            return ""; // 如果不应显示复选框，返回空字符串
        }
        return sprintf('<input type="checkbox" name="ID[]" value="%s" />', $item['ID'] . '|' . $item['posts']['ID']);
    }

    /**
     * 自定义post_title列
     */
    function column_post_title($item)
    {
        $edit = admin_url('post.php?post=' . $item['ID'] . '&action=edit');

        $output    = '';

        // Title.
        $output .= '<strong>' . $item['post_title'] . '</strong>';

        // Get actions.
        $actions = array(
            'edit'   => '<a href="' . esc_url($edit) . '">编辑</a>',
        );

        $row_actions = array();
        foreach ($actions as $action => $link) {
            $row_actions[] = '<span class="' . esc_attr($action) . '">' . $link . '</span>';
        }
        $output .= '<div class="row-actions">' . implode(' | ', $row_actions) . '</div>';

        return $output;
    }

    /**
     * 必须
     * Define table columns 
     * 
     */
    function get_columns()
    {
        $columns = array(
            /**
             * 添加批量操作
             */
            'cb'            => '<input type="checkbox" />',
            'post_title' => 'File',
            'file_url' => 'File URL',
            'include_post' => 'Include Post',
            'attach' => 'Attach'
        );
        return $columns;
    }

    /**
     * 必须
     * bind data with column
     */
    function column_default($item, $column_name)
    {
        // $file_url = wp_get_attachment_url($item['ID']); // 获取媒体文件的完整 URL
        // $posts = $this->find_post_by_media($file_url);
        // var_dump($posts);
        switch ($column_name) {
            case 'post_title':
                return $item[$column_name];
            case 'file_url':
                return $item['file_url'];
            case 'include_post':
                return empty($item['posts']) ? '' : '<a href="' . home_url() . '?p=' . $item['posts']['ID'] . '&preview=true" target="_blank">' . $item['posts']['post_title'] . '</a>';
            case 'attach':
                return empty($item['posts']) ? '' : '<a href="#" class="attach-media-button" data-media-id="' . esc_attr($item['ID']) . '" data-post-id="' . esc_attr($item['posts']['ID']) . '">Attach</a>';
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * 必须
     * Bind table with columns, data and all
     */
    function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns(); //设置排序字段
        $this->_column_headers = array($columns, $hidden, $sortable);

        /* pagination */
        $per_page = 20;
        $current_page = $this->get_pagenum();

        /** 获取表格数据 */
        $this->items = $this->get_table_data($per_page, $current_page);

        /** 获取表格数据总数 */
        $total_items = $this->total_items;
        $this->set_pagination_args(array(
            'total_items' => $total_items, // total number of items
            'per_page'    => $per_page // items to show on a page
        ));
    }

    /**
     * 排序
     * 设置可排序字段
     */
    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'post_title'  => array('post_title', false)
        );
        return $sortable_columns;
    }

    /**
     * 添加批量操作
     */
    public function get_bulk_actions()
    {

        return array(
            'attach' => 'Attach'
        );
    }
}
