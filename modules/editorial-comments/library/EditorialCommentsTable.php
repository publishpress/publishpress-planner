<?php
/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2022 PublishPress
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace PublishPress\EditorialComments;

use PublishPress\Notifications\Traits\Dependency_Injector;
use PP_Editorial_Comments;
use WP_List_Table;

if (! class_exists('WP_List_Table')) {
    include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class EditorialCommentsTable
 *
 * @package PublishPress\EditorialComments
 */
class EditorialCommentsTable extends WP_List_Table
{
    use Dependency_Injector;

    const POSTS_PER_PAGE = 20;

    /**
     * EditorialCommentsTable constructor.
     */
    public function __construct()
    {
        global $status, $page, $post_id;

        //Set parent defaults
        parent::__construct(
            [
                'singular' => 'editorial_comment',    //singular name of the listed records
                'plural' => 'editorial_comments',    //plural name of the listed records
                'ajax' => true        //does this table support ajax?
            ]
        );
    }

    /**
     * @global int    $post_id
     * @global string $comment_type
     * @global string $search
     */
    public function prepare_items()
    {


        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $search = ( isset($_REQUEST['s']) ) ? sanitize_text_field($_REQUEST['s']) : '';

        $post_id = (isset($_REQUEST['p']) && !empty($_REQUEST['p'])) ? absint($_REQUEST['p']) : '';

        $post_type = (isset($_REQUEST['post_type']) && !empty($_REQUEST['post_type'])) ? sanitize_key($_REQUEST['post_type']) : '';

        $user_id = ( isset($_REQUEST['user_id']) && !empty($_REQUEST['user_id']) ) ? absint($_REQUEST['user_id']) : '';

        $orderby = ( isset($_REQUEST['orderby']) ) ? sanitize_text_field($_REQUEST['orderby']) : '';
        $order   = ( isset($_REQUEST['order']) ) ? sanitize_text_field($_REQUEST['order']) : '';

        $comments_per_page = $this->get_per_page();

        if (isset($_REQUEST['number']) ) {
            $number = (int) $_REQUEST['number'];
        } else {
            $number = $comments_per_page + min(8, $comments_per_page); // Grab a few extra.
        }

        $page = $this->get_pagenum();

        if (isset($_REQUEST['start']) ) {
            $start = $_REQUEST['start'];
        } else {
            $start = ( $page - 1 ) * $comments_per_page;
        }

        $args = [
            'type'      => PP_Editorial_Comments::comment_type,
            'status'    => PP_Editorial_Comments::comment_type,
            'search'    => $search,
            'user_id'   => $user_id,
            'offset'    => $start,
            'number'    => $number,
            'post_id'   => $post_id,
            'orderby'   => $orderby,
            'order'     => $order,
            'post_type' => $post_type,
        ];

        $_comments = get_comments($args);

        if (is_array($_comments) ) {
            $this->items       = array_slice($_comments, 0, $comments_per_page);
        }

        $total_comments = get_comments(
            array_merge(
                $args,
                array(
                    'count'  => true,
                    'offset' => 0,
                    'number' => 0,
                )
            )
        );

        $this->set_pagination_args(
            array(
            'total_items' => $total_comments,
            'per_page'    => $comments_per_page,
            )
        );
    }

    /**
     * @param  string $comment_status
     * @return int
     */
    public function get_per_page()
    {
        
        return $this->get_items_per_page('editorial_comments_per_page');
    }

    /**
     * @global string $comment_status
     */
    public function no_items()
    {

        _e('No editorial comments.', 'publishpress');
    }

    /**
     *
     * @param string $which
     */
    protected function extra_tablenav( $which )
    {
        static $has_items;

        if (! isset($has_items) ) {
            $has_items = $this->has_items();
        }

        echo '<div class="alignleft actions">';

        if ('top' === $which ) {
            ob_start();

            //post filter
            $post_id = isset($_GET['p']) ? (int)$_GET['p'] : 0;
            echo '<label class="screen-reader-text" for="feditorial-comment-filter-posts">' . esc_html__('Filter by post', 'publishpress') . '</label>';
            echo '<select class="editorial-comment-filter-posts" id="feditorial-comment-filter-posts" name="p">';
            printf("\t<option value=''>%s</option>", __('All Posts', 'publishpress'));
            if (!empty($post_id)) {
                $post = get_post($post_id);
                printf(
                    "\t<option value='%s'%s>%s</option>\n",
                    esc_attr($post_id),
                    selected(true, true, false),
                    esc_html($post->post_title)
                );
            }
            echo '</select>';

            //users filter
            $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
            echo '<label class="screen-reader-text" for="feditorial-comment-filter-users">' . esc_html__('Filter by user', 'publishpress') . '</label>';
            echo '<select class="editorial-comment-filter-users" id="feditorial-comment-filter-users" name="user_id">';
            printf("\t<option value=''>%s</option>", __('All Users', 'publishpress'));
            if (!empty($user_id)) {
                $user = get_user_by('ID', $user_id);
                printf(
                    "\t<option value='%s'%s>%s</option>\n",
                    esc_attr($user_id),
                    selected(true, true, false),
                    esc_html($user->display_name)
                );
            }
            echo '</select>';

            $output = ob_get_clean();
            echo $output;
            submit_button(__('Filter', 'publishpress'), '', 'filter_action', false, array( 'id' => 'post-query-submit' ));
        }

        echo '</div>';
    }

    /**
     * @global int $post_id
     *
     * @return array
     */
    public function get_columns()
    {

        $columns = array();

        //$columns['cb']   = '<input type="checkbox" />';

        $columns['author']   = __('Author', 'publishpress');
        $columns['comment']  = __('Comment', 'publishpress');

        $columns['response'] = __('Post', 'publishpress');

        $columns['date']     = __('Submitted on', 'publishpress');

        return $columns;
    }

    /**
     * @return array
     */
    protected function get_sortable_columns()
    {
        return [
            'author'   => ['comment_author', true],
            'response' => ['comment_post_ID', true],
            'date'     => ['comment_date', true]
        ];
    }

    /**
     * Get the name of the default primary column.
     *
     * @since 4.3.0
     *
     * @return string Name of the default primary column, in this case, 'comment'.
     */
    protected function get_default_primary_column_name()
    {
        return 'comment';
    }

    /**
     * @param WP_Comment $comment The comment object.
     */
    public function column_comment( $comment )
    {
        echo '<div class="comment-author">';
        $this->column_author($comment);
        echo '</div>';

        comment_text($comment);
        $comment_files = get_comment_meta($comment->comment_ID, '_pp_editorial_comment_files', true);
        if (!empty($comment_files)) {
            $comment_files = explode(" ", $comment_files);
        }?>
        <div class="comment-files">
            <?php if (is_array($comment_files)) : ?>
                <?php foreach ($comment_files as $comment_file_id) : ?>
                    <?php 
                    $media_file = wp_get_attachment_url($comment_file_id);
                    if (!is_wp_error($media_file) && !empty($media_file)) {
                        $file_name = explode('/', $media_file);
                        ?>
                        <div class="editorial-single-file" data-file_id="<?php echo esc_attr($comment_file_id); ?>"> 
                            &dash;
                            <a href="<?php echo esc_url($media_file); ?>" target="blank">
                                <?php echo esc_html(end($file_name)); ?>
                            </a>
                        </div>
                        <?php
                    }
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @global string $comment_status
     *
     * @param WP_Comment $comment The comment object.
     */
    public function column_author( $comment )
    {
        global $publishpress;
        if (isset($publishpress->modules->editorial_comments->options->editorial_comment_name_field)) {
            $field = $publishpress->modules->editorial_comments->options->editorial_comment_name_field;
        } else {
            $field = 'display_name';
        }

        $avatar       = get_avatar($comment->user_id, 32, 'mystery');
        $user_data    = get_userdata($comment->user_id);
        $author_name  = !empty($user_data->$field) ? $user_data->$field : $user_data->display_name;
        $author_name_url = add_query_arg(
            array(
                'page' => PP_Editorial_Comments::MENU_SLUG,
                'user_id' => $comment->user_id
            ),
            admin_url('admin.php')
        );
        echo '<strong>';
        echo $avatar;
        printf('<a href="%1$s">%2$s</a>', esc_url($author_name_url), esc_html($author_name));
        echo '</strong><br />';
    }

    /**
     * @param WP_Comment $comment The comment object.
     */
    public function column_date( $comment )
    {
        $submitted = sprintf(
        /* translators: 1: Comment date, 2: Comment time. */
            __('%1$s at %2$s', 'publishpress'),
            /* translators: Comment date format. See https://www.php.net/manual/datetime.format.php */
            get_comment_date(__('Y/m/d'), $comment),
            /* translators: Comment time format. See https://www.php.net/manual/datetime.format.php */
            get_comment_date(__('g:i a'), $comment)
        );

        echo '<div class="submitted-on">';
        echo $submitted;
        echo '</div>';
    }

    /**
     * @param WP_Comment $comment The comment object.
     */
    public function column_response( $comment )
    {
        $post = get_post($comment->comment_post_ID);

        if (! $post ) {
            return;
        }

        if (current_user_can('edit_post', $post->ID) ) {
            $post_link  = "<a href='" . get_edit_post_link($post->ID) . "' class='comments-edit-item-link'>";
            $post_link .= esc_html(get_the_title($post->ID)) . '</a>';
        } else {
            $post_link = esc_html(get_the_title($post->ID));
        }

        echo '<div class="response-links">';

        if ('attachment' === $post->post_type ) {
            $thumb = wp_get_attachment_image($post->ID, array( 80, 60 ), true);
            if ($thumb ) {
                echo $thumb;
            }
        }

        echo $post_link;

        $post_type_object = get_post_type_object($post->post_type);
        echo "<a href='" . get_permalink($post->ID) . "' class='comments-view-item-link'>" . $post_type_object->labels->view_item . '</a>';
        echo '</div>';
    }

    /**
     * @since 5.9.0 Renamed `$comment` to `$item` to match parent class for PHP 8 named parameter support.
     *
     * @param WP_Comment $item        The comment object.
     * @param string     $column_name The custom column's name.
     */
    public function column_default( $item, $column_name )
    {
        /**
         * Fires when the default column output is displayed for a single row.
         *
         * @since 2.8.0
         *
         * @param string $column_name The custom column's name.
         * @param string $comment_id  The comment ID as a numeric string.
         */
        do_action('manage_comments_custom_column', $column_name, $item->comment_ID);
    }

    /**
     * Generates and display row actions links for the list table.
     *
     * @param object $item The item being acted upon.
     * @param string $column_name Current column name.
     * @param string $primary Primary column name.
     *
     * @return string The row actions HTML, or an empty string if the current column is the primary column.
     */
    protected function handle_row_actions($item, $column_name, $primary)
    {

        $current_user = wp_get_current_user();

        $actions = [];
        
        if (
            ($current_user->user_nicename == $item->comment_author && current_user_can('pp_delete_editorial_comment'))
            || (current_user_can('pp_delete_others_editorial_comment'))
            ) {
            $actions['edit'] = sprintf(
                '<span class="delete"><a href="%s">%s</a></span>',
                esc_url(
                    add_query_arg(
                        [
                            'page'              => PP_Editorial_Comments::MENU_SLUG, 
                            'pp_planner_action' => 'delete_comment', 
                            'comment_id'        => esc_attr($item->comment_ID),
                            '_nonce'            => wp_create_nonce('editorial-comments-delete')
                        ],
                        admin_url('admin.php')
                    )
                ),
                esc_html__('Delete', 'publishpress')
            );
        }

        return $column_name === $primary ? $this->row_actions($actions, false) : '';
    }
}
