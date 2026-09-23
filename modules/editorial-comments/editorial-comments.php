<?php
/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2022 PublishPress
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
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

use PublishPress\EditorialComments\EditorialCommentsTable;
use PublishPress\Notifications\Traits\Dependency_Injector;
use PublishPress\Legacy\Auto_loader;
use PublishPress\Legacy\Util;
use PublishPress\Core\Error;
use PublishPress\Core\Ajax;

if (! class_exists('PP_Editorial_Comments')) {
    /**
     * class PP_Editorial_Comments
     * Threaded commenting in the admin for discussion between writers and editors
     *
     * @author batmoo
     */
    #[\AllowDynamicProperties]
    class PP_Editorial_Comments extends PP_Module
    {
        use Dependency_Injector;

        /**
         * @var string
         */
        const MENU_SLUG = 'pp-editorial-comments';

        // This is comment type used to differentiate editorial comments
        const comment_type = 'editorial-comment';

        public function __construct()
        {
            $this->module_url = $this->get_module_url(__FILE__);
            // Register the module with PublishPress
            $args = [
                'title' => __('Editorial Comments', 'publishpress'),
                'short_description' => false,
                'extended_description' => false,
                'module_url' => $this->module_url,
                'icon_class' => 'dashicons dashicons-admin-comments',
                'slug' => 'editorial-comments',
                'default_options' => [
                    'enabled' => 'off',
                    'post_types' => [
                        'post' => 'on',
                        'page' => 'on',
                    ],
                    'editorial_comment_name_field' => 'display_name',
                ],
                'configure_page_cb' => 'print_configure_view',
                'configure_link_text' => __('Choose Post Types', 'publishpress'),
                'autoload' => false,
                'settings_help_tab' => [
                    'id' => 'pp-editorial-comments-overview',
                    'title' => __('Overview', 'publishpress'),
                    'content' => __(
                        '<p>Editorial comments help you cut down on email overload and keep the conversation close to where it matters: your content. Threaded commenting in the admin, similar to what you find at the end of a blog post, allows writers and editors to privately leave feedback and discuss what needs to be changed before publication.</p><p>Anyone with access to view the story in progress will also have the ability to comment on it. If you have notifications enabled, those following the post will receive an email every time a comment is left.</p>',
                        'publishpress'
                    ),
                ],
                'settings_help_sidebar' => __(
                    '<p><strong>For more information:</strong></p><p><a href="https://publishpress.com/features/editorial-comments/">Editorial Comments Documentation</a></p><p><a href="https://github.com/ostraining/PublishPress">PublishPress on Github</a></p>',
                    'publishpress'
                ),
                'options_page' => true,
            ];

            $this->module = PublishPress()->register_module('editorial_comments', $args);

            Auto_loader::register('\\PublishPress\\EditorialComments\\', __DIR__ . '/library');

            parent::__construct();
        }

        /**
         * Initialize the rest of the stuff in the class if the module is active
         */
        public function init()
        {
            if (false === is_admin()) {
                return;
            }

            add_action('add_meta_boxes', [$this, 'add_post_meta_box']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'add_admin_scripts']);
            add_action('wp_ajax_publishpress_ajax_insert_comment', [$this, 'ajax_insert_comment']);
            add_action('wp_ajax_publishpress_ajax_edit_comment', [$this, 'ajax_edit_comment']);
            add_action('wp_ajax_publishpress_ajax_delete_comment', [$this, 'ajax_delete_comment']);
            add_action('wp_ajax_publishpress_editorial_search_post', [$this, 'ajaxSearchPost']);
            add_action('wp_ajax_publishpress_editorial_search_user', [$this, 'ajaxSearchUser']);
            add_action('admin_init', [$this, 'action_delete_comment']);
            add_filter('removable_query_args', [$this, 'filter_removable_query_args']);

            add_action('publishpress_admin_submenu', [$this, 'action_admin_submenu'], 20);

            // Add Editorial Comments to the calendar if the calendar is activated
            if ($this->module_enabled('calendar')) {
                add_filter('publishpress_calendar_get_post_data', [$this, 'filterCalendarItemData'], 12, 2);
            }
        }

        /**
         * Upgrade our data in case we need to
         *
         * @since 0.7
         */
        public function upgrade($previous_version)
        {
            global $publishpress;

            // Upgrade path to v0.7
            if (version_compare($previous_version, '0.7', '<')) {
                // Technically we've run this code before so we don't want to auto-install new data
                $publishpress->update_module_option($this->module->name, 'loaded_once', true);
            }
        }

        /**
         * Load any of the admin scripts we need but only on the pages we need them
         */
        public function add_admin_scripts()
        {
            global $pagenow;

            $editorial_comment_page = (isset($_GET['page']) && $_GET['page'] === 'pp-editorial-comments') ? true : false;
            $post_type = $this->get_current_post_type();
            $supported_post_types = $this->get_post_types_for_module($this->module);
            if (! in_array($post_type, $supported_post_types) && !$editorial_comment_page) {
                return;
            }

            if (! in_array($pagenow, ['post.php', 'page.php', 'post-new.php', 'page-new.php']) && !$editorial_comment_page) {
                return;
            }

            // Disable the scripts for the post page if the plugin Visual Composer is enabled.
            if (isset($_GET['vcv-action']) && $_GET['vcv-action'] === 'frontend') {
                return false;
            }


            wp_enqueue_script(
                'publishpress-select2',
                plugins_url('common/libs/select2/js/select2-full.min.js', PUBLISHPRESS_FILE_PATH),
                ['jquery'],
                PUBLISHPRESS_VERSION
            );

            wp_enqueue_script(
                'publishpress-editorial-comments',
                $this->module_url . 'lib/editorial-comments.js',
                ['jquery', 'wp-ajax-response', 'publishpress-select2'],
                PUBLISHPRESS_VERSION,
                true
            );
            wp_enqueue_style(
                'publishpress-editorial-comments-css',
                $this->module_url . 'lib/editorial-comments.css',
                false,
                PUBLISHPRESS_VERSION,
                'all'
            );

            wp_enqueue_style(
                'publishpress-select2-css',
                plugins_url('common/libs/select2/css/select2-full.min.css', PUBLISHPRESS_FILE_PATH),
                false,
                PUBLISHPRESS_VERSION,
                'all'
            );

            wp_localize_script(
                'publishpress-editorial-comments',
                'publishpressEditorialCommentsParams',
                [
                    'loadingImgSrc' => esc_url(admin_url('/images/wpspin_light.gif')),
                    'removeText'    => __('Remove', 'publishpress'),
                    'allPosts'      => __('All Posts', 'publishpress'),
                    'allUsers'      => __('All Users', 'publishpress'),
                    'nonce'         => wp_create_nonce('editorial-comments-admin'),
                ]
            );

            wp_set_script_translations( 'publishpress-editorial-comments', 'publishpress' );

            $thread_comments = (int)get_option('thread_comments'); ?>
            <script type="text/javascript">
                var pp_thread_comments = <?php echo ($thread_comments) ? esc_html__($thread_comments) : 0; ?>;
            </script>
            <?php
        }

        /**
         * Add necessary things to the admin menu
         */
        public function action_admin_submenu()
        {
            $publishpress = $this->get_service('publishpress');

            // Main Menu
            $hook = add_submenu_page(
                $publishpress->get_menu_slug(),
                esc_html__('Editorial Comments', 'publishpress'),
                esc_html__('Editorial Comments', 'publishpress'),
                'edit_posts',
                self::MENU_SLUG,
                [$this, 'render_admin_page'],
                20
            );

            add_action('load-' . $hook, [$this, 'addScreenOptions']);
        }

        public function addScreenOptions()
        {
            $option = 'per_page';
            $args = [
                'label' => 'Number of items per page',
                'default' => EditorialCommentsTable::POSTS_PER_PAGE,
                'option' => 'editorial_comments_per_page',
            ];
            add_screen_option($option, $args);
        }

        /**
         * Create the content overview view. This calls lots of other methods to do its work. This will
         * output any messages, create the table navigation, then print the columns based on
         * get_num_columns(), which will in turn print the stories themselves.
         */
        public function render_admin_page()
        {
            $publishpress = $this->get_service('publishpress');
            $publishpress->settings->print_default_header($publishpress->modules->editorial_comments);

            $commentTable = new EditorialCommentsTable();
            $commentTable->views();
            ?>
            <div class="wrap">
                <div class="pp-columns-wrapper<?php echo (!Util::isPlannersProActive()) ? ' pp-enable-sidebar' : '' ?>">
                    <div class="pp-column-left">
                        <?php
                        if (isset($_REQUEST['s']) && $search_str = esc_attr(wp_unslash(sanitize_text_field($_REQUEST['s'])))) {
                            /* translators: %s: search keywords */
                            printf(' <span class="description">' . esc_html__('Search results for &#8220;%s&#8221;', 'publishpress') . '</span>', esc_html($search_str));
                        }
                        //Fetch, prepare, sort, and filter our data...
                        $commentTable->prepare_items();

                        $page = '';
                        if (isset($_REQUEST['page'])) {
                            $page = sanitize_text_field($_REQUEST['page']);
                        }
                        ?>
                        <form class="search-form wp-clearfix" method="get">
                                <input type="hidden" name="page" value="<?php
                                echo esc_attr($page) ?>"/>
                            <?php $commentTable->search_box(esc_html__('Search Comments', 'publishpress'), 'editorial-comments'); ?>
                        </form>
                        <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
                        <form id="log-filter" method="get">
                            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
                            <input type="hidden" name="page" value="<?php
                                echo esc_attr($page) ?>"/>

                            <!-- Now we can render the completed list table -->
                            <?php
                            $commentTable->display() ?>
                        </form>
                    </div><!-- .pp-column-left -->
                    <?php if (!Util::isPlannersProActive()) { ?>
                        <div class="pp-column-right">
                            <?php Util::pp_pro_sidebar(); ?>
                        </div><!-- .pp-column-right -->
                    <?php } ?>
                </div><!-- .pp-columns-wrapper -->
            </div>
            <?php

            $publishpress->settings->print_default_footer($publishpress->modules->editorial_comments);
        }

        public function ajaxSearchPost()
        {
            $ajax = Ajax::getInstance();

            if (! isset($_GET['nonce']) || ! wp_verify_nonce(
                    sanitize_text_field($_GET['nonce']),
                    'editorial-comments-admin'
                )) {
                $ajax->sendJsonError(Error::ERROR_CODE_INVALID_NONCE);
            }

            if (!current_user_can('edit_posts')) {
                $ajax->sendJsonError(Error::ERROR_CODE_ACCESS_DENIED);
            }

            global $wpdb;

            $commentType = self::comment_type;
            $search = isset($_GET['search']) ? $wpdb->esc_like(sanitize_text_field($_GET['search'])) : '';
            $search = '%' . $search . '%';

            $posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT c.comment_post_id AS 'ID', p.post_title AS 'post_title'
                FROM {$wpdb->comments} AS c
                LEFT JOIN {$wpdb->posts} AS p ON (c.comment_post_id = p.ID)
                WHERE c.comment_type = %s
                AND (p.post_title LIKE %s OR p.ID LIKE %s)",
                    $commentType,
                    $search,
                    $search
                )
            );


            $output = [
                'results' => [],
            ];

            if (! empty($posts)) {
                foreach ($posts as $post) {
                    $output['results'][] = [
                        'id' => $post->ID,
                        'text' => $post->post_title,
                    ];
                }
            }

            $output['pagination'] = [
                'more' => false,
            ];

            $ajax->sendJson($output);
        }

        public function ajaxSearchUser()
        {
            global $wpdb;

            $ajax = Ajax::getInstance();

            if (! isset($_GET['nonce']) || ! wp_verify_nonce(
                    sanitize_text_field($_GET['nonce']),
                    'editorial-comments-admin'
                )) {
                $ajax->sendJsonError(Error::ERROR_CODE_INVALID_NONCE);
            }

            if (!current_user_can('edit_posts')) {
                $ajax->sendJsonError(Error::ERROR_CODE_ACCESS_DENIED);
            }

            $queryText = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

            $output = [
                'results' => [],
            ];
            $output['pagination'] = [
                'more' => false,
            ];

            /**
             * @param array $results
             * @param string $searchText
             */
            $results = apply_filters('publishpress_search_authors_results_pre_search', [], $queryText);

            if (! empty($results)) {
                $output['results'] = $results;
                $ajax->sendJson($output);
            }

            // Define the custom SQL query to get users who have written comments
            $commentType = self::comment_type;

            $userSql = "SELECT DISTINCT u.ID, u.display_name
            FROM {$wpdb->users} AS u
            INNER JOIN {$wpdb->comments} AS c
            ON u.ID = c.user_id
            WHERE c.comment_type = %s";
            $queryArgs = [$commentType];

            if (!empty($queryText)) {
                $searchLike = '%' . $wpdb->esc_like($queryText) . '%';
                $userSql .= " AND (user_login LIKE %s
                    OR user_url LIKE %s
                    OR user_email LIKE %s
                    OR user_nicename LIKE %s
                    OR display_name LIKE %s)";
                $queryArgs = array_merge($queryArgs, array_fill(0, 5, $searchLike));
            }

            $userSql .= " ORDER BY u.display_name LIMIT 20";

            $users = $wpdb->get_results($wpdb->prepare($userSql, $queryArgs));

            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->ID,
                    'text' => $user->display_name,
                ];
            }

            $output['results'] = $results;
            $ajax->sendJson($output);
        }

        /**
         * Add the editorial comments metabox to enabled post types
         *
         * @uses add_meta_box()
         */
        public function add_post_meta_box()
        {
            $supported_post_types = $this->get_post_types_for_module($this->module);

            foreach ($supported_post_types as $post_type) {
                add_meta_box(
                    'publishpress-editorial-comments',
                    __('Editorial Comments', 'publishpress'),
                    [$this, 'editorial_comments_meta_box'],
                    $post_type,
                    'normal',
                    apply_filters('pp_editorial_comments_metabox_priority', 'high')
                );
            }
        }

        /**
         * Get the total number of editorial comments for a post
         *
         * @param int $id Unique post ID
         *
         * @return int $comment_count Number of editorial comments for a post
         */
        private function get_editorial_comment_count($id)
        {
            global $wpdb;
            $comment_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_type = %s",
                    $id,
                    self::comment_type
                )
            );
            if (! $comment_count) {
                $comment_count = 0;
            }

            return $comment_count;
        }

        public function editorial_comments_meta_box()
        {
            global $post, $post_ID; ?>
            <div id="pp-comments_wrapper">
                <a name="editorialcomments"></a>

                <?php
                // Show comments only if not a new post
                if (! in_array($post->post_status, ['new', 'auto-draft'])) :

                    // Unused since switched to wp_list_comments
                    $editorial_comments = pp_get_comments_plus(
                        [
                            'post_id' => $post->ID,
                            'comment_type' => self::comment_type,
                            'orderby' => 'comment_date',
                            'order' => 'ASC',
                            'status' => self::comment_type,
                        ]
                    ); ?>

                    <ul id="pp-comments">
                        <?php
                        // We use this so we can take advantage of threading and such

                        wp_list_comments(
                            [
                                'type' => self::comment_type,
                                'callback' => [$this, 'the_comment'],
                                'end-callback' => '__return_false',
                            ],
                            $editorial_comments
                        ); ?>
                    </ul>

                    <?php
                    $this->the_comment_form(); ?>

                <?php
                else :
                    ?>
                    <p><?php
                        _e(
                            'You can add editorial comments to a post once you\'ve saved it for the first time.',
                            'publishpress'
                        ); ?></p>
                <?php
                endif; ?>
                <div class="clear"></div>
            </div>
            <div class="clear"></div>
            <?php
        }

        /**
         * Displays the main commenting form
         */
        private function the_comment_form()
        {
            global $post; ?>
            <a href="#" id="pp-comment_respond" onclick="editorialCommentReply.open();return false;"
               class="button hide-if-no-js" title=" <?php
            _e(
                'Add an editorial comment',
                'publishpress'
            ); ?>"><span><?php
                    _e('Add an editorial comment', 'publishpress'); ?></span></a>

            <!-- Reply form, hidden until reply clicked by user -->
            <div id="pp-replyrow" style="display: none;">
                <div class="pp-replyattachment">
                    <a href="#" class="button editorial-comment-file-upload">
                        <?php _e('Attach file', 'publishpress') ?>
                    </a>
                </div>

                <div id="pp-replycontainer">
                    <textarea id="pp-replycontent" name="replycontent" cols="40" rows="5"></textarea>
                </div>

                <div id="pp-replysubmit">
                    <div class="editorial-attachments"></div>
                    <a class="button pp-replysave button-primary alignright" href="#comments-form">
                        <span id="pp-replybtn"><?php
                            _e('Add Comment', 'publishpress') ?></span>
                    </a>
                    <a class="pp-replycancel button-secondary alignright"
                       href="#comments-form"><?php
                        _e('Cancel', 'publishpress'); ?></a>
                    <img alt="Sending comment..." src="<?php
                    echo esc_url(admin_url('/images/wpspin_light.gif')); ?>"
                         class="alignright" style="display: none;" id="pp-comment_loading"/>
                    <br class="clear" style="margin-bottom:35px;"/>
                    <span style="display: none;" class="error"></span>
                </div>

                <input type="hidden" value="" id="pp-comment_parent" name="pp-comment_parent"/>
                <input type="hidden" value="" id="pp-comment_files" name="pp-comment_files"/>
                <input type="hidden" name="pp-post_id" id="pp-post_id" value="<?php
                echo esc_attr($post->ID); ?>"/>

                <?php
                wp_nonce_field('comment', 'pp_comment_nonce', false); ?>

                <br class="clear"/>
            </div>

            <?php
        }

        /**
         * Displays a single comment
         */
        public function the_comment($theComment, $args, $depth)
        {
            global $comment;

            // Get current user
            $user = wp_get_current_user();

            // Update the global var for the comment.
            // phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
            $comment = $theComment;
            // phpcs:enable

            $actions = [];

            $actions_string_escaped = '';
            // Comments can only be added by users that can edit the post
            if (current_user_can('edit_post', $theComment->comment_post_ID)) {
                $actions['reply'] = '<a onclick="editorialCommentReply.open(\'' . (int)$theComment->comment_ID . '\',\'' . (int)$theComment->comment_post_ID . '\');return false;" class="vim-r hide-if-no-js" title="' . esc_html__(
                        'Reply to this comment',
                        'publishpress'
                    ) . '" href="#">' . esc_html__('Reply', 'publishpress') . '</a>';

                if (
                    ($user->ID == $theComment->user_id && current_user_can(
                            'pp_edit_editorial_comment'
                        )
                        || current_user_can('pp_edit_others_editorial_comment')
                    )
                ) {
                    $actions['quickedit'] = '<a onclick="editorialCommentEdit.open(\'' . (int)$theComment->comment_ID . '\');" href="javascript:void(0);">' . esc_html__(
                            'Edit',
                            'publishpress'
                        ) . '</a>';
                }

                if (
                    ($user->ID == $theComment->user_id && current_user_can('pp_delete_editorial_comment')
                        || current_user_can('pp_delete_others_editorial_comment')
                    )
                ) {
                    $actions['delete'] = '<a onclick="editorialCommentDelete.open(\'' . (int)$theComment->comment_ID . '\');" href="javascript:void(0);">' . esc_html__(
                            'Delete',
                            'publishpress'
                        ) . '</a>';
                }

                $i = 0;
                foreach ($actions as $action => $link) {
                    ++$i;

                    if ($i > 1) {
                        $actions_string_escaped .= ' | ';
                    }

                    $action .= ' hide-if-no-js';

                    $actions_string_escaped .= "&nbsp;<span class='" . esc_attr($action) . "'>$link</span>";
                }
            }

            ?>

            <li id="comment-<?php
            echo esc_attr($theComment->comment_ID); ?>" <?php
            comment_class(
                [
                    'comment-item',
                    wp_get_comment_status($theComment->comment_ID),
                ]
            ); ?> data-parent="<?php
            echo $theComment->comment_parent; ?>" data-id="<?php
            echo $theComment->comment_ID; ?>" data-post-id="<?php
            echo $theComment->comment_post_ID; ?>">


                <?php
                echo get_avatar($theComment->comment_author_email, 50); ?>

                <div class="post-comment-wrap">
                    <h5 class="comment-meta">
                        <span class="comment-author"><?php
                            comment_author_email_link($theComment->comment_author); ?></span>
                        <span class="meta">
                            <?php
                            printf(
                                esc_html__(
                                    'said on %1$s at %2$s',
                                    'publishpress'
                                ),
                                get_comment_date(get_option('date_format')),
                                get_comment_time()
                            );
                            ?>
                        </span>
                    </h5>

                    <div class="comment-content"><?php
                        comment_text(); ?></div>
                        <?php
                        $comment_files = get_comment_meta($theComment->comment_ID, '_pp_editorial_comment_files', true);
                        if (!empty($comment_files)) {
                            $comment_files = explode(" ", $comment_files);
                        }
                        ?>
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
                                        <span class="editorial-comment-edit-file-remove">
                                            <?php echo __('Remove', 'publishpress'); ?>
                                        </span>
                                    </div>
                                    <?php
                                }
                                ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="row-actions"><?php
                        echo $actions_string_escaped; ?></div>
                </div>
            </li>
            <?php
        }

        /**
         * Handles AJAX insert comment
         */
        public function ajax_insert_comment()
        {
            global $current_user, $user_ID;

            // Verify nonce
            if (! isset($_POST['_nonce'])
                || ! wp_verify_nonce(sanitize_key($_POST['_nonce']), 'comment')
            ) {
                wp_die(
                    esc_html__(
                        "Nonce check failed. Please ensure you're supposed to be adding editorial comments.",
                        'publishpress'
                    )
                );
            }

            if (! isset($_POST['post_id']) || ! isset($_POST['parent']) || ! isset($_POST['content'])) {
                wp_die(esc_html__('Invalid comment data', 'publishpress'));
            }

            wp_get_current_user();

            $post_id = absint($_POST['post_id']);
            $parent = absint($_POST['parent']);
            $comment_files = sanitize_text_field($_POST['comment_files']);

            // Only allow the comment if user can edit post
            // @TODO: allow contributors to add comments as well (?)
            if (! current_user_can('edit_post', $post_id)) {
                wp_die(
                    esc_html__(
                        'Sorry, you don\'t have the privileges to add editorial comments. Please talk to your Administrator.',
                        'publishpress'
                    )
                );
            }

            // Verify that comment was actually entered
            $comment_content = esc_html(trim($_POST['content']));
            if (! $comment_content) {
                wp_die(esc_html__("Please enter a comment.", 'publishpress'));
            }

            // Check that we have a post_id and user logged in
            if ($post_id && $current_user) {
                // set current time
                $time = current_time('mysql', $gmt = 0);

                // phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
                $author_ip = '';
                if (isset($_SERVER['REMOTE_ADDR'])) {
                    $author_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
                }

                $author_agent = '';
                if (isset($_SERVER['HTTP_USER_AGENT'])) {
                    $author_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'], FILTER_VALIDATE_IP);
                }
                // phpcs:enable

                // Set comment data
                $data = [
                    'comment_post_ID' => (int)$post_id,
                    'comment_author' => esc_sql($current_user->display_name),
                    'comment_author_email' => esc_sql($current_user->user_email),
                    'comment_author_url' => esc_sql($current_user->user_url),
                    'comment_content' => wp_kses(
                        $comment_content,
                        [
                            'a' => ['href' => [], 'title' => []],
                            'b' => [],
                            'i' => [],
                            'strong' => [],
                            'em' => [],
                            'u' => [],
                            'del' => [],
                            'blockquote' => [],
                            'sub' => [],
                            'sup' => [],
                        ]
                    ),
                    'comment_type' => esc_sql(self::comment_type),
                    'comment_parent' => (int)$parent,
                    'user_id' => (int)$user_ID,
                    'comment_author_IP' => esc_sql($author_ip),
                    'comment_agent' => esc_sql($author_agent),
                    'comment_date' => esc_sql($time),
                    'comment_date_gmt' => esc_sql($time),
                    // Set to -1?
                    'comment_approved' => esc_sql(self::comment_type),
                ];

                $data = apply_filters('pp_pre_insert_editorial_comment', $data);

                // Insert Comment
                $comment_id = wp_insert_comment($data);
                $comment = get_comment($comment_id);
                // Add comment files
                if (!empty(trim($comment_files))) {
                    update_comment_meta($comment_id, '_pp_editorial_comment_files', rtrim($comment_files));
                }

                // Register actions -- will be used to set up notifications and other modules can hook into this
                if ($comment_id) {
                    do_action('pp_post_insert_editorial_comment', $comment);
                }

                // Prepare response
                $response = new WP_Ajax_Response();

                ob_start();
                $this->the_comment($comment, '', '');
                $comment_list_item = ob_get_contents();
                ob_end_clean();

                $response->add(
                    [
                        'what' => 'comment',
                        'id' => $comment_id,
                        'data' => $comment_list_item,
                        'action' => ($parent) ? 'reply' : 'new',
                    ]
                );

                $response->send();
            } else {
                wp_die(
                    esc_html__(
                        'There was a problem of some sort. Try again or contact your administrator.',
                        'publishpress'
                    )
                );
            }
        }

        /**
         * Handles AJAX edit comment
         */
        public function ajax_edit_comment()
        {
            global $current_user;

            // Verify nonce
            if (! isset($_POST['_nonce'])
                || ! wp_verify_nonce(sanitize_key($_POST['_nonce']), 'comment')
            ) {
                wp_die(
                    esc_html__(
                        "Nonce check failed. Please ensure you're supposed to be editing editorial comments.",
                        'publishpress'
                    ),
                    '',
                    ['response' => 403]
                );
            }

            if (! isset($_POST['comment_id']) || ! isset($_POST['content']) || ! isset($_POST['post_id'])) {
                wp_die(
                    esc_html__('Invalid comment data', 'publishpress'),
                    '',
                    ['response' => 400]
                );
            }

            wp_get_current_user();

            $comment_id = absint($_POST['comment_id']);
            $post_id = absint($_POST['post_id']);
            $comment_files = sanitize_text_field($_POST['comment_files']);

            // Only allow the comment if user can edit post
            if (! current_user_can('edit_post', $post_id)) {
                wp_die(
                    esc_html__(
                        'Sorry, you don\'t have the privileges to edit editorial comments. Please talk to your Administrator.',
                        'publishpress'
                    ),
                    '',
                    ['response' => 403]
                );
            }

            $theComment = get_comment($comment_id);

            if (
                ! ($current_user->ID == $theComment->user_id && current_user_can(
                        'pp_edit_editorial_comment'
                    ))
                && ! current_user_can('pp_edit_others_editorial_comment')
            ) {
                wp_die(
                    esc_html__(
                        'Sorry, you don\'t have the privileges to edit this editorial comment. Please talk to your Administrator.',
                        'publishpress'
                    ),
                    '',
                    ['response' => 403]
                );
            }

            // Verify that comment was actually entered
            $comment_content = trim(sanitize_text_field($_POST['content']));
            if (! $comment_content) {
                wp_die(
                    esc_html__("Please enter a comment.", 'publishpress'),
                    '',
                    ['response' => 400]
                );
            }

            // Check that we have a post_id and user logged in
            if ($post_id && $comment_id && $current_user) {
                $comment_content = wp_kses(
                    $comment_content,
                    [
                        'a' => ['href' => [], 'title' => []],
                        'b' => [],
                        'i' => [],
                        'strong' => [],
                        'em' => [],
                        'u' => [],
                        'del' => [],
                        'blockquote' => [],
                        'sub' => [],
                        'sup' => [],
                    ]
                );

                $data = [
                    'comment_ID' => (int)$comment_id,
                    'comment_content' => $comment_content,
                ];

                $data = apply_filters('pp_pre_edit_editorial_comment', $data);

                $status = wp_update_comment($data);

                // update comment files
                update_comment_meta((int)$comment_id, '_pp_editorial_comment_files', rtrim($comment_files));

                if ($status == 1) {
                    do_action('pp_post_edit_editorial_comment', $comment_id);
                }

                // Prepare response

                $theComment = get_comment($comment_id);

                $response = [
                    'id' => $comment_id,
                    'content' => apply_filters('comment_text', $theComment->comment_content, $theComment),
                    'action' => 'edit',
                ];

                wp_send_json($response);
            } else {
                wp_die(
                    esc_html__(
                        'There was a problem of some sort. Try again or contact your administrator.',
                        'publishpress'
                    ),
                    '',
                    ['response' => 403]
                );
            }
        }

        /**
         * Handles AJAX delete comment
         */
        public function ajax_delete_comment()
        {
            global $current_user;

            // Verify nonce
            if (! isset($_POST['_nonce'])
                || ! wp_verify_nonce(sanitize_key($_POST['_nonce']), 'comment')
            ) {
                wp_die(
                    esc_html__(
                        "Nonce check failed. Please ensure you're supposed to be editing editorial comments.",
                        'publishpress'
                    ),
                    '',
                    ['response' => 403]
                );
            }

            if (! isset($_POST['comment_id'])) {
                wp_die(
                    esc_html__('Invalid comment data', 'publishpress'),
                    '',
                    ['response' => 400]
                );
            }

            wp_get_current_user();

            $comment_id = absint($_POST['comment_id']);

            $theComment = get_comment($comment_id);

            // Only allow the comment if user can edit post
            if (! current_user_can('edit_post', $theComment->comment_post_ID)) {
                wp_die(
                    esc_html__(
                        'Sorry, you don\'t have the privileges to edit editorial comments. Please talk to your Administrator.',
                        'publishpress'
                    ),
                    '',
                    ['response' => 403]
                );
            }

            if (
                ! ($current_user->ID == $theComment->user_id && current_user_can(
                        'pp_delete_editorial_comment'
                    ))
                && ! current_user_can('pp_delete_others_editorial_comment')
            ) {
                wp_die(
                    esc_html__(
                        'Sorry, you don\'t have the privileges to delete this editorial comment. Please talk to your Administrator.',
                        'publishpress'
                    ),
                    '',
                    ['response' => 403]
                );
            }

            // Check that we have a post_id and user logged in
            if ($comment_id && $current_user) {
                // Check if the comment has any reply and deny the delete action.
                $replies = get_comments([
                    'parent' => $comment_id,
                    'count' => true,
                    'type' => self::comment_type,
                    'status' => self::comment_type,
                ]);

                if (! empty($replies)) {
                    wp_die(
                        esc_html__(
                            'Sorry, you can\'t delete this editorial comment because it has some replies.',
                            'publishpress'
                        ),
                        '',
                        ['response' => 403]
                    );
                }

                do_action('pp_pre_delete_editorial_comment', $comment_id);

                $deleted = wp_trash_comment($comment_id);

                if ($deleted) {
                    do_action('pp_post_delete_editorial_comment', $comment_id);
                }

                $response = [
                    'id' => $comment_id,
                    'action' => 'delete',
                ];

                wp_send_json($response);
            } else {
                wp_die(
                    esc_html__(
                        'There was a problem of some sort. Try again or contact your administrator.',
                        'publishpress'
                    ),
                    '',
                    ['response' => 403]
                );
            }
        }

        /**
         * Handles action delete comment
         */
        public function action_delete_comment()
        {

            if (
                (!is_admin() || !is_user_logged_in())
                || (!isset($_GET['page']) || (isset($_GET['page']) && $_GET['page'] !== 'pp-editorial-comments'))
                || (!isset($_GET['pp_planner_action']) || (isset($_GET['pp_planner_action']) && $_GET['pp_planner_action'] !== 'delete_comment'))
            ) {
                return;
            }

            // Verify nonce
            if (! isset($_GET['_nonce'])
                || ! wp_verify_nonce(sanitize_key($_GET['_nonce']), 'editorial-comments-delete')
            ) {
                add_action('admin_notices', function () {
                    echo '<div class="notice error"><p>' . esc_html__(
                        "Nonce check failed. Please ensure you're supposed to be editing editorial comments.",
                        'publishpress'
                    ) . '</p></div>';
                });
                return;
            }

            if (! isset($_GET['comment_id'])) {
                add_action('admin_notices', function () {
                    echo '<div class="notice error"><p>' . esc_html__('Invalid comment data', 'publishpress') . '</p></div>';
                });
                return;
            }

            $current_user = wp_get_current_user();

            $comment_id = absint($_GET['comment_id']);

            $theComment = get_comment($comment_id);


            if (
                ! ($current_user->ID == $theComment->user_id && current_user_can(
                        'pp_delete_editorial_comment'
                    ))
                && ! current_user_can('pp_delete_others_editorial_comment')
            ) {
                add_action('admin_notices', function () {
                    echo '<div class="notice error"><p>' . esc_html__(
                        'Sorry, you don\'t have the privileges to delete this editorial comment. Please talk to your Administrator.',
                        'publishpress'
                    ) . '</p></div>';
                });
                return;
            }

            if ($comment_id) {
                do_action('pp_pre_delete_editorial_comment', $comment_id);

                $deleted = wp_delete_comment($comment_id, true);

                if ($deleted) {
                    do_action('pp_post_delete_editorial_comment', $comment_id);
                }

                add_action('admin_notices', function () {
                    echo '<div class="notice updated"><p>' . esc_html__(
                        'Comment deleted successfully.',
                        'publishpress'
                    ) . '</p></div>';
                });
                return;
            } else {
                add_action('admin_notices', function () {
                    echo '<div class="notice error"><p>' . esc_html__(
                        'There was a problem of some sort. Try again or contact your administrator.',
                        'publishpress'
                    ) . '</p></div>';
                });
                return;
            }
        }

        /**
         * Filter removable args
         *
         * @param array $args
         * @return array
         */
        public function filter_removable_query_args (array $args) {

            return array_merge(
                $args,
                [
                    'pp_planner_action',
                    'comment_id',
                    '_nonce'
                ]
            );
        }

        /**
         * Register settings for editorial comments so we can partially use the Settings API
         * (We use the Settings API for form generation, but not saving)
         *
         * @since 0.7
         */
        public function register_settings()
        {
            add_settings_section(
                $this->module->options_group_name . '_general',
                false,
                '__return_false',
                $this->module->options_group_name
            );
            add_settings_field(
                'post_types',
                __('Enable for these post types:', 'publishpress'),
                [$this, 'settings_post_types_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );
            add_settings_field(
                'editorial_comment_name_field',
                esc_html__('Comment author name field:', 'publishpress'),
                [$this, 'settings_editorial_comment_name_field_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );
        }

        /**
         * Chose the post types for editorial comments
         *
         * @since 0.7
         */
        public function settings_post_types_option()
        {
            global $publishpress;
            $publishpress->settings->helper_option_custom_post_type($this->module);
        }

        /**
         * Editorial comment name field
         *
         */
        public function settings_editorial_comment_name_field_option()
        {
            $options = [
                'user_login'    => __('Nickname', 'publishpress'),
                'user_nicename' => __('Username', 'publishpress'),
                'display_name'  => __('Display Name', 'publishpress'),
                'first_name'     => __('First Name', 'publishpress'),
                'last_name'     => __('Last Name', 'publishpress'),
                'user_email'    => __('Email', 'publishpress'),
                'user_url'      => __('User Url', 'publishpress'),
            ];
            echo '<select id="editorial_comment_name_field" name="' . esc_attr(
                    $this->module->options_group_name
                ) . '[editorial_comment_name_field]">';
            foreach ($options as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"';
                echo selected($this->module->options->editorial_comment_name_field, $value);
                echo '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }

        /**
         * Validate our user input as the settings are being saved
         *
         * @since 0.7
         */
        public function settings_validate($new_options)
        {
            // Whitelist validation for the post type options
            if (! isset($new_options['post_types'])) {
                $new_options['post_types'] = [];
            }
            $new_options['post_types'] = $this->clean_post_type_options(
                $new_options['post_types'],
                $this->module->post_type_support
            );

            return $new_options;
        }

        /**
         * Settings page for editorial comments
         *
         * @since 0.7
         */
        public function print_configure_view()
        {
            global $publishpress; ?>
            <form class="basic-settings"
                  action="<?php
                  echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
                <?php
                settings_fields($this->module->options_group_name); ?>
                <?php
                do_settings_sections($this->module->options_group_name); ?>
                <?php
                echo '<input id="publishpress_module_name" name="publishpress_module_name[]" type="hidden" value="' . esc_attr(
                        $this->module->name
                    ) . '" />'; ?>
                <p class="submit"><?php
                    submit_button(null, 'primary', 'submit', false); ?></p>
                <?php
                echo '<input name="publishpress_module_name[]" type="hidden" value="' . esc_attr(
                        $this->module->name
                    ) . '" />'; ?>
                <?php
                wp_nonce_field('edit-publishpress-settings'); ?>
            </form>
            <?php
        }

        /**
         * If the PublishPress Calendar is enabled, add the editorial comment count to the post overlay.
         *
         * @param array $data Additional data fields to include on the calendar
         * @param WP_Post $post
         *
         * @return array $calendar_fields Calendar fields with our viewable Editorial Fields added
         * @uses  apply_filters('publishpress_calendar_get_post_data')
         *
         * @since 0.7
         */
        public function filterCalendarItemData($data, $post)
        {
            // Make sure we respect which post type we're on
            if (! in_array($post->post_type, $this->get_post_types_for_module($this->module))) {
                return $data;
            }

            $data['fields']['editorial-comments'] = [
                'label' => $this->module->title,
                'value' => (int)$this->get_editorial_comment_count($post->ID),
                'editable' => false,
                'type' => 'number',
            ];

            return $data;
        }
    }
}

/**
 * Retrieve a list of comments -- overloaded from get_comments and with mods by filosofo (SVN Ticket #10668)
 *
 * @param mixed $args Optional. Array or string of options to override defaults.
 *
 * @return array List of comments.
 */
function pp_get_comments_plus($args = '')
{
    global $wpdb;

    $defaults = [
        'author_email' => '',
        'ID' => '',
        'karma' => '',
        'number' => '',
        'offset' => '',
        'orderby' => '',
        'order' => 'DESC',
        'parent' => '',
        'post_ID' => '',
        'post_id' => 0,
        'status' => '',
        'type' => '',
        'user_id' => '',
    ];

    $args = wp_parse_args($args, $defaults);
    extract($args, EXTR_SKIP);

    // $args can be whatever, only use the args defined in defaults to compute the key
    $key = md5(serialize(compact(array_keys($defaults))));
    $last_changed = wp_cache_get('last_changed', 'comment');
    if (! $last_changed) {
        $last_changed = time();
        wp_cache_set('last_changed', $last_changed, 'comment');
    }
    $cache_key = "get_comments:$key:$last_changed";

    if ($cache = wp_cache_get($cache_key, 'comment')) {
        return $cache;
    }

    $post_id = absint($post_id);

    if ('hold' == $status) {
        $approved = "comment_approved = '0'";
    } elseif ('approve' == $status) {
        $approved = "comment_approved = '1'";
    } elseif ('spam' == $status) {
        $approved = "comment_approved = 'spam'";
    } elseif (! empty($status)) {
        $approved = $wpdb->prepare("comment_approved = %s", $status);
    } else {
        $approved = "(comment_approved = '0' OR comment_approved = '1')";
    }

    $order = ('ASC' == $order) ? 'ASC' : 'DESC';

    if (! empty($orderby)) {
        $ordersby = is_array($orderby) ? $orderby : preg_split('/[,\s]/', $orderby);
        $ordersby = array_intersect(
            $ordersby,
            [
                'comment_agent',
                'comment_approved',
                'comment_author',
                'comment_author_email',
                'comment_author_IP',
                'comment_author_url',
                'comment_content',
                'comment_date',
                'comment_date_gmt',
                'comment_ID',
                'comment_karma',
                'comment_parent',
                'comment_post_ID',
                'comment_type',
                'user_id',
            ]
        );
        $orderby = empty($ordersby) ? 'comment_date_gmt' : implode(', ', $ordersby);
    } else {
        $orderby = 'comment_date_gmt';
    }

    $number = absint($number);
    $offset = absint($offset);

    if (! empty($number)) {
        if ($offset) {
            $number = 'LIMIT ' . $offset . ',' . $number;
        } else {
            $number = 'LIMIT ' . $number;
        }
    } else {
        $number = '';
    }

    $post_where = '';

    if (! empty($post_id)) {
        $post_where .= $wpdb->prepare('comment_post_ID = %d AND ', $post_id);
    }
    if ('' !== $author_email) {
        $post_where .= $wpdb->prepare('comment_author_email = %s AND ', $author_email);
    }
    if ('' !== $karma) {
        $post_where .= $wpdb->prepare('comment_karma = %d AND ', $karma);
    }
    if ('comment' == $type) {
        $post_where .= "comment_type = '' AND ";
    } elseif (! empty($type)) {
        $post_where .= $wpdb->prepare('comment_type = %s AND ', $type);
    }
    if ('' !== $parent) {
        $post_where .= $wpdb->prepare('comment_parent = %d AND ', $parent);
    }
    if ('' !== $user_id) {
        $post_where .= $wpdb->prepare('user_id = %d AND ', $user_id);
    }

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $comments = $wpdb->get_results(
        "SELECT * FROM $wpdb->comments WHERE $post_where $approved ORDER BY $orderby $order $number"
    );
    // phpcs:enable
    wp_cache_add($cache_key, $comments, 'comment');

    return $comments;
}
