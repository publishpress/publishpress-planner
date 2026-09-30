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
 * (at your option ) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

if (! defined('PP_NOTIFICATION_USE_CRON')) {
    define('PP_NOTIFICATION_USE_CRON', false);
}

use PublishPress\Notifications\Traits\Dependency_Injector;

if (! class_exists('PP_Notifications')) {
    /**
     * Class PP_Notifications
     * Notifications for PublishPress and more
     */
    #[\AllowDynamicProperties]
    class PP_Notifications extends PP_Module
    {
        use Dependency_Injector;

        const MODULE_NAME = 'notifications';

        const MENU_SLUG = 'pp-notifications';

        // Taxonomy name used to store users which will be notified for changes in the posts.
        public $notify_user_taxonomy = 'pp_notify_user';

        // Taxonomy name used to store roles which will be notified for changes in the posts.
        public $notify_role_taxonomy = 'pp_notify_role';

        // Taxonomy name used to store groups which will be notified for changes in the posts.
        public $notify_group_taxonomy = 'pp_notify_group';

        // Taxonomy name used to store emails which will be notified for changes in the posts.
        public $notify_email_taxonomy = 'pp_notify_email';

        public $module;

        public $edit_post_subscriptions_cap = 'edit_post_subscriptions';

        /**
         * Register the module with PublishPress but don't do anything else
         */
        public function __construct()
        {
            // Register the module with PublishPress
            $this->module_url = $this->get_module_url(__FILE__);
            $args = [
                'title' => __('Notifications', 'publishpress'),
                'short_description' => false,
                'extended_description' => false,
                'module_url' => $this->module_url,
                'icon_class' => 'dashicons dashicons-email',
                'slug' => 'notifications',
                'default_options' => [
                    'enabled' => 'on',
                    'post_types' => [
                        'post' => 'on',
                        'page' => 'on',
                    ],
                    'notify_author_by_default' => '1',
                    'notify_current_user_by_default' => '1',
                    'blacklisted_taxonomies' => '',
                ],
                'configure_page_cb' => 'print_configure_view',
                'post_type_support' => 'pp_notification',
                'autoload' => false,
                'settings_help_tab' => [
                    'id' => 'pp-notifications-overview',
                    'title' => __('Overview', 'publishpress'),
                    'content' => __(
                        '<p>Notifications ensure you keep up to date with progress your most important content. Users can be subscribed to notifications on a post one by one or by selecting roles.</p><p>When enabled, notifications can be sent when a post changes status or an editorial comment is left by a writer or an editor.</p>',
                        'publishpress'
                    ),
                ],
                'settings_help_sidebar' => __(
                    '<p><strong>For more information:</strong></p><p><a href="https://publishpress.com/features/notifications/">Notifications Documentation</a></p><p><a href="https://github.com/ostraining/PublishPress">PublishPress on Github</a></p>',
                    'publishpress'
                ),
                'options_page' => true,
            ];
            $this->module = PublishPress()->register_module('notifications', $args);
        }

        /**
         * Initialize the notifications class if the plugin is enabled
         */
        public function init()
        {
            // Register our taxonomies for managing relationships
            $this->register_taxonomies();

            $this->setDefaultCapabilities();

            // Allow users to use a different user capability for editing post subscriptions
            $this->edit_post_subscriptions_cap = apply_filters(
                'pp_edit_post_subscriptions_cap',
                $this->edit_post_subscriptions_cap
            );

            if (is_admin()) {
                add_action('admin_init', [$this, 'register_settings']);

                // Javascript and CSS if we need it
                add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
                add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);

                // Add a "Notify" link to posts
                if (apply_filters('pp_notifications_show_notify_link', true)) {
                    // A little extra JS for the Notify button
                    add_action('admin_head', [$this, 'action_admin_head_notify_js']);
                    // Manage Posts
                    add_filter('post_row_actions', [$this, 'filter_post_row_actions'], 10, 2);
                    add_filter('page_row_actions', [$this, 'filter_post_row_actions'], 10, 2);
                    // Calendar and Content Overview
                    add_filter('pp_calendar_item_actions', [$this, 'filter_post_row_actions'], 10, 2);
                    add_filter('pp_story_budget_item_actions', [$this, 'filter_post_row_actions'], 10, 2);
                }

                /*add_filter(
                    'publishpress_calendar_get_post_data',
                    [$this, 'filterCalendarGetPostData'],
                    10,
                    2
                );*/

                // Ajax for saving notification updates
                add_action('wp_ajax_pp_notifications_user_post_subscription', [$this, 'handle_user_post_subscription']);
            }

            // Saving post actions
            // self::save_post_subscriptions() is hooked into transition_post_status so we can ensure role data
            // is properly saved before sending notifs
            add_action(
                'transition_post_status',
                [$this, 'notification_status_change'],
                PP_NOTIFICATION_PRIORITY_STATUS_CHANGE,
                3
            );

            add_filter(
                'pp_notification_auto_subscribe_post_author',
                [$this, 'filter_pp_notification_auto_subscribe_post_author'],
                10,
                2
            );
            add_filter(
                'pp_notification_auto_subscribe_current_user',
                [$this, 'filter_pp_notification_auto_subscribe_current_user'],
                10,
                2
            );

            add_action('pp_post_insert_editorial_comment', [$this, 'notification_comment']);
            add_action('delete_user', [$this, 'delete_user_action']);
            add_action('pp_send_scheduled_notification', [$this, 'send_single_email'], 10, 5);

            add_action('save_post', [$this, 'action_save_post'], 10);

            add_action('pp_send_notification_status_update', [$this, 'send_notification_status_update']);
            add_action('pp_send_notification_comment', [$this, 'send_notification_comment']);
        }

        /**
         * Load the capabilities onto users the first time the module is run
         *
         * @since 0.7
         */
        public function install()
        {
            // Considering we could be moving from Edit Flow, we need to migrate the following users.
            $this->migrateLegacyFollowingTerms();
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
                // Migrate whether notifications were enabled or not
                if ($enabled = get_option('publishpress_notifications_enabled')) {
                    $enabled = 'on';
                } else {
                    $enabled = 'off';
                }
                $publishpress->update_module_option($this->module->name, 'enabled', $enabled);
                delete_option('publishpress_notifications_enabled');
                // Migrate whether to always notify the admin
                // @todo: Remove after sometime. The setting always notify admin was removed.
                if ($always_notify_admin = get_option('publishpress_always_notify_admin')) {
                    $always_notify_admin = 'on';
                } else {
                    $always_notify_admin = 'off';
                }
                $publishpress->update_module_option($this->module->name, 'always_notify_admin', $always_notify_admin);
                delete_option('publishpress_always_notify_admin');

                // Technically we've run this code before so we don't want to auto-install new data
                $publishpress->update_module_option($this->module->name, 'loaded_once', true);
            }

            if (version_compare($previous_version, '1.10', '<=')) {
                $this->migrateLegacyFollowingTerms();
            }
        }

        private function setDefaultCapabilities()
        {
            $role = get_role('administrator');

            $capabilities = [
                'edit_pp_notif_workflow',
                'read_pp_notif_workflow',
                'delete_pp_notif_workflow',
                'edit_pp_notif_workflows',
                'edit_others_pp_notif_workflows',
                'publish_pp_notif_workflows',
                'read_private_pp_notif_workflows',
                'edit_pp_notif_workflows',
            ];

            foreach ($capabilities as $capability) {
                $role->add_cap($capability);
            }
        }


        protected function migrateLegacyFollowingTerms()
        {
            global $wpdb;

            // Migrate Following Users
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}term_taxonomy SET taxonomy = %s WHERE taxonomy = 'following_users'",
                    $this->notify_user_taxonomy
                )
            );
        }

        /**
         * Register the taxonomies we use to manage relationships
         *
         * @since 0.7
         *
         * @uses  register_taxonomy()
         */
        public function register_taxonomies()
        {
            // Load the currently supported post types so we only register against those
            $supported_post_types = $this->get_post_types_for_module($this->module);

            $args = [
                'hierarchical' => false,
                'update_count_callback' => '_update_post_term_count',
                'label' => false,
                'query_var' => false,
                'rewrite' => false,
                'public' => false,
                'show_ui' => false,
            ];

            register_taxonomy(
                $this->notify_user_taxonomy,
                $supported_post_types,
                wp_parse_args(
                    [
                        'label' => __('Notify User', 'publishpress'),
                        'labels' => [
                            'name' => __('Notify Users', 'publishpress'),
                            'singular_name' => __('Notify User', 'publishpress'),
                        ],
                    ],
                    $args
                )
            );

            register_taxonomy(
                $this->notify_role_taxonomy,
                $supported_post_types,
                wp_parse_args(
                    [
                        'label' => __('Notify Role', 'publishpress'),
                        'labels' => [
                            'name' => __('Notify Roles', 'publishpress'),
                            'singular_name' => __('Notify Role', 'publishpress'),
                        ],
                    ],
                    $args
                )
            );

            if (defined('PRESSPERMIT_VERSION')) {
                register_taxonomy(
                    $this->notify_group_taxonomy,
                    $supported_post_types,
                    wp_parse_args(
                        [
                            'label' => __('Notification Group', 'publishpress'),
                            'labels' => [
                                'name' => __('Notification Groups', 'publishpress'),
                                'singular_name' => __('Notification Group', 'publishpress'),
                            ],
                        ],
                        $args
                    )
                );
            }

            register_taxonomy(
                $this->notify_email_taxonomy,
                $supported_post_types,
                wp_parse_args(
                    [
                        'label' => __('Notify Email', 'publishpress'),
                        'labels' => [
                            'name' => __('Notify Emails', 'publishpress'),
                            'singular_name' => __('Notify Email', 'publishpress'),
                        ],
                    ],
                    $args
                )
            );
        }

        /**
         * Enqueue necessary admin scripts
         *
         * @since 0.7
         *
         * @uses  wp_enqueue_script()
         */
        public function enqueue_admin_scripts()
        {
            if ($this->is_whitelisted_functional_view()) {

                wp_enqueue_script(
                    'publishpress-select2',
                    PUBLISHPRESS_URL . 'common/libs/select2/js/select2-full.min.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_script(
                    'publishpress-notifications-js',
                    $this->module_url . 'assets/notifications.js',
                    [
                        'jquery',
                        'publishpress-select2'
                    ],
                    PUBLISHPRESS_VERSION,
                    true
                );
            }
        }

        /**
         * Whether or not the current page is a user-facing PublishPress View
         *
         * @param string $module_name (Optional) Module name to check against
         *
         * @since 0.7
         *
         * @todo  Think of a creative way to make this work
         *
         */
        protected function is_whitelisted_functional_view($module_name = null)
        {
            global $current_screen;

            if (! is_object($current_screen)) {
                return false;
            }

            return $current_screen->base === 'post';
        }

        /**
         * Enqueue necessary admin styles, but only on the proper pages
         *
         * @since 0.7
         *
         * @uses  wp_enqueue_style()
         */
        public function enqueue_admin_styles()
        {
            if ($this->is_whitelisted_functional_view() || $this->is_whitelisted_settings_view()) {
                wp_enqueue_style('jquery-listfilterizer');
                wp_enqueue_style(
                    'publishpress-notifications-css',
                    $this->module->module_url . 'assets/notifications.css',
                    false,
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_style(
                    'publishpress-select2',
                    PUBLISHPRESS_URL . 'common/libs/select2/css/select2-full.min.css',
                    false,
                    PUBLISHPRESS_VERSION
                );
            }
        }

        /**
         * JS required for the Notify link to work
         *
         * @since 0.8
         */
        public function action_admin_head_notify_js()
        {
            ?>
            <script type='text/javascript'>
                (function ($) {
                    $(document).ready(function ($) {
                        /**
                         * Action to Notify / Stop Notifying posts on the manage posts screen
                         */
                        $('.wp-list-table, #pp-calendar-view, #pp-story-budget-wrap').on('click', '.pp_notify_link a', function (e) {

                            e.preventDefault();

                            var link = $(this);

                            $.ajax({
                                type: 'GET',
                                url: link.attr('href'),
                                success: function (data) {
                                    if ('success' === data.status) {
                                        link.attr('href', data.message.link);
                                        link.attr('title', data.message.title);
                                        link.text(data.message.text);
                                    }
                                    // @todo expose the error somehow
                                }
                            });

                            return false;
                        });
                    });
                })(jQuery);
            </script>
            <?php
        }

        /**
         * Add a "Notify" link to supported post types Manage Posts view
         *
         * @param array $actions Any existing item actions
         * @param int|object $post Post id or object
         *
         * @return array     $actions   The follow link has been appended
         * @since 0.8
         *
         */
        public function filter_post_row_actions($actions, $post)
        {
            $post = get_post($post);

            if (! in_array($post->post_type, $this->get_post_types_for_module($this->module))) {
                return $actions;
            }

            if (! current_user_can($this->edit_post_subscriptions_cap) && ! current_user_can(
                    'edit_post',
                    $post->ID
                )) {
                return $actions;
            }

            $parts = $this->get_notify_action_parts($post);
            $actions['pp_notify_link'] = '<a title="' . esc_attr($parts['title']) . '" href="' . esc_url(
                    $parts['link']
                ) . '">' . $parts['text'] . '</a>';

            return $actions;
        }

        /**
         * Get an action parts for a user to set Notify or Stop Notify for a post
         *
         * @since 0.8
         */
        private function get_notify_action_parts($post)
        {
            $args = [
                'action' => 'pp_notifications_user_post_subscription',
                'post_id' => $post->ID,
            ];

            $user_to_notify = $this->get_users_to_notify($post->ID);

            if (in_array(wp_get_current_user()->user_login, $user_to_notify)) {
                $args['method'] = 'stop_notifying';
                $title_text = __('Click to stop being notified on updates for this post', 'publishpress');
                $link_text = __('Stop notifying me', 'publishpress');
            } else {
                $args['method'] = 'start_notifying';
                $title_text = __('Click to start being notified on updates for this post', 'publishpress');
                $link_text = __('Notify me', 'publishpress');
            }

            // wp_nonce_url() has encoding issues: http://core.trac.wordpress.org/ticket/20771
            $args['_wpnonce'] = wp_create_nonce('pp_notifications_user_post_subscription');

            return [
                'title' => $title_text,
                'text' => $link_text,
                'link' => add_query_arg($args, admin_url('admin-ajax.php')),
            ];
        }

        /**
         * Add the subscriptions meta box to relevant post types
         */
        public function add_post_meta_box()
        {
            if (! current_user_can($this->edit_post_subscriptions_cap)) {
                return;
            }

            $role_post_types = $this->get_post_types_for_module($this->module);

            foreach ($role_post_types as $post_type) {
                add_meta_box(
                    'publishpress-notifications',
                    __('Notifications', 'publishpress'),
                    [$this, 'notifications_meta_box'],
                    $post_type,
                    'side',
                    'high'
                );
            }
        }

        public function getPostID($post)
        {
            return $post->ID;
        }

        /**
         * Outputs box used to subscribe users and roles to Posts
         *
         * @todo add_cap to set subscribers for posts; default to Admin and editors
         */
        public function notifications_meta_box()
        {
            global $post;

            $followersWorkflows = $this->get_workflows_related_to_followers();
            $activeWorkflows = $this->get_workflows_related_to_post($post);

            $followersWorkflows = array_map([$this, 'getPostID'], $followersWorkflows);

            $postType = get_post_type_object($post->post_type);

            $notify_me_style = empty($followersWorkflows) ? 'display: none;' : '';
            ?>
            <div id="pp_post_notify_box">
                <a name="subscriptions"></a>
                <div style="<?php echo esc_attr($notify_me_style); ?>">
                    <p>
                        <?php
                        esc_html_e(
                            'Enter users, roles, or email addresses that should receive notifications for this post.',
                            'publishpress'
                        ); ?><?php
                        if (! empty($followersWorkflows)) : ?><a class="notification-followers" href="javascript:void(0);" title="<?php _e('Show active notifications', 'publishpress');?>">&sup1;</a><?php
                        endif; ?>
                    </p>

                    <div id="pp_post_notify_users_box">
                        <?php
                        $users_to_notify = $this->get_users_to_notify($post->ID, 'id');
                        $roles_to_notify = $this->get_roles_to_notify($post->ID, 'slugs');
                        $groups_to_notify = $this->get_groups_to_notify($post->ID, 'slugs');
                        $emails_to_notify = $this->get_emails_to_notify($post->ID);

                        $selected = array_merge($users_to_notify, $roles_to_notify, $groups_to_notify, $emails_to_notify);

                        $select_form_args = [
                            'list_class' => 'pp_post_notify_list',
                        ];
                        $this->users_select_form($selected, $select_form_args); ?>

                    </div>

                    <style>
                        a.notification-followers, a.notification-followers:active, a.notification-followers:visited, a.notification-followers:link {
                            text-decoration: none;
                        }
                    </style>

                    <script type="text/javascript">
                    /* <![CDATA[ */
                    jQuery(document).ready(function ($) {
                        $('#pp_post_notify_box a.notification-followers').on('click', function() {
                            $('div.pp_post_notify_workflows').show();
                        });
                    });
                    /* ]]> */
                    </script>

                    <?php
                    if (empty($followersWorkflows)) : ?>
                        <p class="no-workflows"><?php
                            echo esc_html__(
                                'This won\'t have any effect unless you have at least one workflow targeting the "Users who selected "Notify me" for the content" option.',
                                'publishpress'
                            ); ?></p>
                    <?php
                    endif; ?>
                </div>

                <?php
                if (current_user_can('edit_pp_notif_workflows')) : ?>
                    <div class="pp_post_notify_workflows" style="display: none">
                        <?php
                        if (! empty($activeWorkflows)) : ?>
                            <h3><?php
                                echo esc_html__('Active Notifications', 'publishpress'); ?></h3>

                            <ul>
                                <?php
                                foreach ($activeWorkflows as $workflow) : ?>
                                    <li>
                                        <a href="<?php
                                        echo esc_url(
                                            admin_url(
                                                'post.php?post=' . $workflow->workflow_post->ID . '&action=edit&classic-editor'
                                            )
                                        ); ?>"
                                           target="_blank">
                                            <?php
                                            echo esc_html($workflow->workflow_post->post_title); ?><?php
                                            if (in_array(
                                                $workflow->workflow_post->ID,
                                                $followersWorkflows
                                            )): ?>&sup1;<?php
                                            endif; ?>
                                        </a>
                                    </li>
                                <?php
                                endforeach; ?>
                            </ul>

                            <?php if (!empty($followersWorkflows)):?>
                            <p>
                                &sup1; = <?php _e('Workflow has users enrolled', 'publishpress');?>
                            </p>
                            <?php endif;?>
                        <?php
                        else: ?>
                            <p class="no-workflows"><?php
                                echo sprintf(
                                    esc_html__(
                                        'No active notifications found for this %s.',
                                        'publishpress'
                                    ),
                                    esc_html($postType->labels->singular_name)
                                ); ?></p>
                        <?php
                        endif; ?>
                    </div>
                <?php
                endif; ?>

                <?php
                /**
                 * @param WP_Post $post
                 */
                do_action('publishpress_notif_post_metabox', $post);
                ?>

                <div class="clear"></div>

                <?php
                // Extra protection against autosaves
                ?>
                <input type="hidden" name="pp_save_notify" value="1"/>

                <?php
                wp_nonce_field('save_roles', 'pp_notifications_nonce', false); ?>
            </div>

            <?php
        }

        /**
         * Return workflows with the "Notify to followers" set as true.
         *
         * @return array
         */
        protected function get_workflows_related_to_followers()
        {
            $publishpress = PublishPress();

            $meta_query = [
                'relation' => 'OR',
                [
                    'key' => '_psppno_tofollower',
                    'value' => 1,
                    'compare' => '=',
                ],
            ];

            $workflows = $publishpress->improved_notifications->get_workflows($meta_query);

            return $workflows;
        }

        /**
         * Return workflows where the current post type is selected.
         *
         * @param $post
         *
         * @return mixed
         *
         * @throws Exception
         */
        protected function get_workflows_related_to_post($post)
        {
            $workflows_controller = $this->get_service('workflows_controller');

            $post_status = apply_filters('publishpress_notifications_status', $post->post_status, $post);

            $args = [
                'event' => '',
                'params' => [
                    'post_id' => $post->ID,
                    'new_status' => $post_status,
                    'old_status' => $post_status,
                    'ignore_event' => true,
                ],
            ];

            return apply_filters('publishpress_post_notification_get_workflows', $workflows_controller->get_filtered_workflows($args), $post);
        }

        public function action_save_post($postId)
        {
            if (! isset($_POST['pp_notifications_nonce']) || ! wp_verify_nonce(
                    sanitize_text_field($_POST['pp_notifications_nonce']),
                    'save_roles'
                )) {
                return;
            }

            // Remove current users
            $terms = get_the_terms($postId, $this->notify_user_taxonomy);
            $users = [];
            if (! empty($terms)) {
                foreach ($terms as $term) {
                    $users[] = $term->term_id;
                }
            }
            wp_remove_object_terms($postId, $users, $this->notify_user_taxonomy);


            // Remove current roles
            $terms = get_the_terms($postId, $this->notify_role_taxonomy);
            $roles = [];
            if (! empty($terms)) {
                foreach ($terms as $term) {
                    $roles[] = $term->term_id;
                }
            }
            wp_remove_object_terms($postId, $roles, $this->notify_role_taxonomy);

            if (defined('PRESSPERMIT_VERSION')) {
                // Remove current groups
                $terms = get_the_terms($postId, $this->notify_group_taxonomy);
                $groups = [];
                if (! empty($terms)) {
                    foreach ($terms as $term) {
                        $groups[] = $term->term_id;
                    }
                }
                wp_remove_object_terms($postId, $groups, $this->notify_group_taxonomy);
            }

            // Remove current emails
            $terms = get_the_terms($postId, $this->notify_email_taxonomy);
            $emails = [];
            if (! empty($terms)) {
                foreach ($terms as $term) {
                    $emails[] = $term->term_id;
                }
            }
            wp_remove_object_terms($postId, $emails, $this->notify_email_taxonomy);

            if (apply_filters('pp_notification_auto_subscribe_current_user', true)) {
                if (! isset($_POST['to_notify'])) {
                    $_POST['to_notify'] = [];
                }
                if (! array_search(get_current_user_id(), $_POST['to_notify'])) {
                    $_POST['to_notify'][] = get_current_user_id();
                }
            }

            if (isset($_POST['to_notify'])) {
                foreach ($_POST['to_notify'] as $id) {
                    if (is_numeric($id)) {
                        // User id
                        $this->post_set_users_to_notify($postId, (int)$id, true);
                    } else {
                        $id = sanitize_text_field($id);

                        // Is an email address?
                        if (strpos($id, '@') > 0) {
                            $this->post_set_emails_to_notify($postId, $id, true);

                        } elseif (0 === strpos($id, 'group-')) {
                            $this->post_set_groups_to_notify($postId, $id, true);
                        } else {
                            // Role name
                            $this->post_set_roles_to_notify($postId, $id, true);
                        }
                    }
                }
            }
        }

        /**
         * Handle a request to update a user's post subscription
         *
         * @since 0.8
         */
        public function handle_user_post_subscription()
        {
            if (! isset($_GET['_wpnonce'])
                || ! wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'pp_notifications_user_post_subscription')
            ) {
                $this->print_ajax_response('error', $this->module->messages['nonce-failed']);
            }

            if (! isset($_GET['method']) || ! current_user_can($this->edit_post_subscriptions_cap)) {
                $this->print_ajax_response('error', $this->module->messages['invalid-permissions']);
            }

            if (! isset($_GET['post_id']) || empty((int)$_GET['post_id'])) {
                $this->print_ajax_response('error', $this->module->messages['missing-post']);
            }

            $post = get_post((int)$_GET['post_id']);

            if (! $post) {
                $this->print_ajax_response('error', $this->module->messages['missing-post']);
            }

            if ('start_notifying' === $_GET['method']) {
                $retval = $this->post_set_users_to_notify($post, get_current_user_id());
            } else {
                $retval = $this->post_set_users_stop_notify($post, get_current_user_id());
            }

            if (is_wp_error($retval)) {
                $this->print_ajax_response('error', $retval->get_error_message());
            }

            $this->print_ajax_response('success', (object )$this->get_notify_action_parts($post));
        }

        /**
         * @param $default
         * @param $context
         *
         * @return bool
         */
        public function filter_pp_notification_auto_subscribe_post_author($default, $context)
        {
            if (! isset($this->module->options->notify_author_by_default)) {
                return $default;
            }

            return (bool)$this->module->options->notify_author_by_default;
        }

        /**
         * @param $default
         *
         * @return bool
         */
        public function filter_pp_notification_auto_subscribe_current_user($default)
        {
            if (! isset($this->module->options->notify_current_user_by_default)) {
                return $default;
            }

            return (bool)$this->module->options->notify_current_user_by_default;
        }

        public function filterCalendarGetPostData($postData, $post)
        {
            if (! current_user_can($this->edit_post_subscriptions_cap)) {
                return $postData;
            }

            $user_to_notify = $this->get_users_to_notify($post->ID);

            if (in_array(wp_get_current_user()->user_login, $user_to_notify)) {
                $link = [
                    'args' => ['method' => 'stop_notifying'],
                    'label' => __('Stop notifying me', 'publishpress'),
                    'title' => __('Click to stop being notified on updates for this post', 'publishpress'),
                ];
            } else {
                $link = [
                    'args' => ['method' => 'start_notifying'],
                    'label' => __('Notify me', 'publishpress'),
                    'title' => __('Click to start being notified on updates for this post', 'publishpress'),
                ];
            }

            $link['action'] = 'pp_notifications_user_post_subscription';
            $link['args']['_wpnonce'] = wp_create_nonce('pp_notifications_user_post_subscription');
            $link['args']['post_id'] = $post->ID;

            $postData['links']['notify'] = $link;

            return $postData;
        }

        /**
         * Set up and send post status change a notification
         */
        public function notification_status_change($new_status, $old_status, $post)
        {
            global $publishpress;


            // Kill switch for notification
            if (! apply_filters(
                    'pp_notification_status_change',
                    $new_status,
                    $old_status,
                    $post
                ) || ! apply_filters(
                    "pp_notification_{$post->post_type}_status_change",
                    $new_status,
                    $old_status,
                    $post
                )) {
                return false;
            }

            $supported_post_types = $this->get_post_types_for_module($this->module);
            if (! in_array($post->post_type, $supported_post_types)) {
                return;
            }

            // No need to notify if it's a revision, auto-draft, or if post status wasn't changed
            $ignored_statuses = apply_filters(
                'pp_notification_ignored_statuses',
                [$old_status, 'inherit', 'auto-draft'],
                $post->post_type
            );

            if (! in_array($new_status, $ignored_statuses)) {
                $args = [
                    'new_status' => $new_status,
                    'old_status' => $old_status,
                    'post' => $post,
                ];

                do_action('pp_send_notification_status_update', $args);
            }
        }

        /**
         * Set up and set editorial comment notification email
         */
        public function notification_comment($comment)
        {
            $post = get_post($comment->comment_post_ID);

            $supported_post_types = $this->get_post_types_for_module($this->module);
            if (! in_array($post->post_type, $supported_post_types)) {
                return;
            }

            // Kill switch for notification
            if (! apply_filters('pp_notification_editorial_comment', $comment, $post)) {
                return false;
            }

            $current_user = wp_get_current_user();

            $post_id = $post->ID;
            $post_type = get_post_type_object($post->post_type)->labels->singular_name;
            $post_title = pp_draft_or_post_title($post_id);

            // Set the post author to be notified for the post but make it filterable
            if (apply_filters('pp_notification_auto_subscribe_post_author', true, 'comment')) {
                $this->post_set_users_to_notify($post, (int )$post->post_author);
            }

            $blogname = get_bloginfo('name');

            // Send the notification
            $args = [
                'blogname' => $blogname,
                'post' => $post,
                'post_title' => $post_title,
                'post_id' => $post_id,
                'post_type' => $post_type,
                'current_user' => $current_user,
                'comment' => $comment,
            ];

            do_action('pp_send_notification_comment', $args);
        }

        private function get_notification_footer($post)
        {
            $body = "";
            $body .= "\r\n--------------------\r\n";
            $body .= sprintf(
                __('You are receiving this email because you are subscribed to "%s".', 'publishpress'),
                pp_draft_or_post_title($post->ID)
            );
            $body .= "\r\n";
            // phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date
            $body .= sprintf(__('This email was sent %s.', 'publishpress'), date('r'));
            // phpcs:enable
            $body .= "\r\n \r\n";
            $body .= get_bloginfo('name') . " | " . home_url('/') . " | " . admin_url('/') . "\r\n";

            return $body;
        }

        /**
         * send_email()
         *
         * @return array
         */
        public function send_email($action, $post, $subject, $message, $message_headers = '', $recipients = null, $attachments = [])
        {
            $deliveryResult = [];

            if (is_null($recipients)) {
                // Get list of email recipients -- set them CC
                $recipients = $this->_get_notification_recipients($post, true);
            }

            if ($recipients && ! is_array($recipients)) {
                $recipients = explode(',', $recipients);
            }

            $subject = apply_filters('pp_notification_send_email_subject', $subject, $action, $post);
            $message = apply_filters('pp_notification_send_email_message', $message, $action, $post);
            $message_headers = apply_filters(
                'pp_notification_send_email_message_headers',
                $message_headers,
                $action,
                $post
            );

            if (PP_NOTIFICATION_USE_CRON) {
                $this->schedule_emails($recipients, $subject, $message, $message_headers, 1, $attachments);
            } elseif (! empty($recipients)) {
                foreach ($recipients as $recipient) {
                    $deliveryResult[$recipient] = $this->send_single_email(
                        $recipient,
                        $subject,
                        $message,
                        $message_headers,
                        $attachments
                    );
                }
            }

            return $deliveryResult;
        }

        /**
         * Schedules emails to be sent in succession
         *
         * @param mixed $recipients Individual email or array of emails
         * @param string $subject Subject of the email
         * @param string $message Body of the email
         * @param string $message_headers . (optional ) Message headers
         * @param int $time_offset (optional ) Delay in seconds per email
         * @param array $attachments . (optional ) Message attachments
         */
        private function schedule_emails($recipients, $subject, $message, $message_headers = '', $time_offset = 1, $attachments = [])
        {
            $recipients = (array)$recipients;

            $send_time = time();

            foreach ($recipients as $recipient) {
                wp_schedule_single_event(
                    $send_time,
                    'pp_send_scheduled_notification',
                    [$recipient, $subject, $message, $message_headers, $attachments]
                );
                $send_time += $time_offset;
            }
        }

        /**
         * Sends an individual email
         *
         * @param mixed $to Email to send to
         * @param string $subject Subject of the email
         * @param string $message Body of the email
         * @param string $message_headers . (optional ) Message headers
         * @param array $attachments . (optional ) Message attachments
         *
         * @return bool
         */
        public function send_single_email($to, $subject, $message, $message_headers = '', $attachments = [])
        {
            return wp_mail($to, $subject, $message, $message_headers, $attachments);
        }

        /**
         * Returns a list of recipients for a given post
         *
         * @param $post   object
         * @param $string bool Whether to return recipients as comma-delimited string or array
         *
         * @return string|array
         */
        private function _get_notification_recipients($post, $string = false)
        {
            $post_id = $post->ID;
            if (! $post_id) {
                return [];
            }

            $authors = [];
            $admins = [];
            $role_users = [];

            // Get users and roles to notify
            $roles = $this->get_roles_to_notify($post_id, 'slugs');
            foreach ((array )$roles as $role_id) {
                $users = get_users(
                    [
                        'role' => $role_id,
                    ]
                );

                if (! empty($users)) {
                    foreach ($users as $user) {
                        if (is_user_member_of_blog($user->ID)) {
                            $role_users[] = $user->user_email;
                        }
                    }
                }
            }

            $users = $this->get_users_to_notify($post_id, 'user_email');

            // Merge arrays and filter any duplicates
            $recipients = array_merge($authors, $admins, $users, $role_users);
            $recipients = array_unique($recipients);

            // Process the recipients for this email to be sent
            foreach ($recipients as $key => $user_email) {
                // Get rid of empty email entries
                if (empty($recipients[$key])) {
                    unset($recipients[$key]);
                }
                // Don't send the email to the current user unless we've explicitly indicated they should receive it
                if (false === apply_filters(
                        'publishpress_notify_current_user',
                        false
                    ) && wp_get_current_user()->user_email == $user_email) {
                    unset($recipients[$key]);
                }
            }

            // Filter to allow further modification of recipients
            $recipients = apply_filters('pp_notification_recipients', $recipients, $post, $string);

            // If string set to true, return comma-delimited
            if ($string && is_array($recipients)) {
                return implode(',', $recipients);
            } else {
                return $recipients;
            }
        }

        /**
         * Set a user or users to be notified for a post
         *
         * @param int|object $post Post object or ID
         * @param string|array $users User or users to subscribe to post updates
         * @param bool $append Whether users should be added to pp_notify_user list or replace existing list
         *
         * @return true|WP_Error     $response  True on success, WP_Error on failure
         */
        private function post_set_users_to_notify($post, $users, $append = true)
        {
            $post = get_post($post);
            if (! $post) {
                return new WP_Error('missing-post', $this->module->messages['missing-post']);
            }

            if (! is_array($users)) {
                $users = [$users];
            }

            $user_terms = [];

            foreach ($users as $user) {
                if (is_int($user)) {
                    $user = get_user_by('id', $user);
                } elseif (is_string($user)) {
                    $user = get_user_by('login', $user);
                }

                if (! is_object($user)) {
                    continue;
                }

                $name = $user->user_login;

                // Add user as a term if they don't exist
                $term = $this->add_term_if_not_exists($name, $this->notify_user_taxonomy);

                if (! is_wp_error($term)) {
                    $user_terms[] = $name;
                }
            }

            $set = wp_set_object_terms($post->ID, $user_terms, $this->notify_user_taxonomy, $append);

            if (is_wp_error($set)) {
                return $set;
            } else {
                return true;
            }
        }

        /**
         * Set a role or roles to be notified for a post
         *
         * @param int|object $post Post object or ID
         * @param string|array $roles Role or roles to subscribe to post updates
         * @param bool $append Whether roles should be added to pp_notify_role list or replace existing list
         *
         * @return true|WP_Error     $response  True on success, WP_Error on failure
         */
        private function post_set_roles_to_notify($post, $roles, $append = true)
        {
            $post = get_post($post);
            if (! $post) {
                return new WP_Error('missing-post', $this->module->messages['missing-post']);
            }

            if (! is_array($roles)) {
                $roles = [$roles];
            }

            $role_terms = [];

            foreach ($roles as $role) {
                $role = get_role($role);

                if (! is_object($role)) {
                    continue;
                }

                // Add user as a term if they don't exist
                $term = $this->add_term_if_not_exists($role->name, $this->notify_role_taxonomy);

                if (! is_wp_error($term)) {
                    $role_terms[] = $role->name;
                }
            }

            $set = wp_set_object_terms($post->ID, $role_terms, $this->notify_role_taxonomy, $append);

            if (is_wp_error($set)) {
                return $set;
            } else {
                return true;
            }
        }

        /**
         * Set a group or groups to be notified for a post
         *
         * @param int|object $post Post object or ID
         * @param string|array $groups Group or groups to subscribe to post updates
         * @param bool $append Whether groups should be added to pp_notify_role list or replace existing list
         *
         * @return true|WP_Error     $response  True on success, WP_Error on failure
         */
        private function post_set_groups_to_notify($post, $groups, $append = true)
        {
            $post = get_post($post);
            if (! $post) {
                return new WP_Error('missing-post', $this->module->messages['missing-post']);
            }

            if (!class_exists('PublishPress\Permissions\API')) {
                return;
            }

            if (! is_array($groups)) {
                $groups = [$groups];
            }

            $role_terms = [];

            foreach ($groups as $group_id) {
                if (0 === strpos($group_id, 'group-')) {
                    $group_id = str_replace('group-', '', $group_id);
                }

                if ($group = \PublishPress\Permissions\API::getGroup($group_id)) {
                    // Add user as a term if they don't exist
                    $slug = 'group-' . $group_id;
                    $term = $this->add_term_if_not_exists($group->name, $this->notify_group_taxonomy, compact('slug'));

                    if (! is_wp_error($term)) {
                        $role_terms[] = $slug;
                    }
                }
            }

            $set = wp_set_object_terms($post->ID, $role_terms, $this->notify_group_taxonomy, $append);

            if (is_wp_error($set)) {
                return $set;
            } else {
                return true;
            }
        }

        /**
         * Set a non-user or non-users to be notified for a post
         *
         * @param int|object $post Post object or ID
         * @param string|array $emails Role or roles to subscribe to post updates
         * @param bool $append Whether roles should be added to pp_notify_role list or replace existing list
         *
         * @return true|WP_Error     $response  True on success, WP_Error on failure
         */
        private function post_set_emails_to_notify($post, $emails, $append = true)
        {
            $post = get_post($post);
            if (! $post) {
                return new WP_Error('missing-post', $this->module->messages['missing-post']);
            }

            if (! is_array($emails)) {
                $emails = [$emails];
            }

            $email_terms = [];

            foreach ($emails as $string) {
                // Do we have the name/email separator?
                $separatorPos = strpos($string, '/');
                if ($separatorPos > 0) {
                    $email = trim(substr($string, $separatorPos + 1, strlen($string)));
                } else {
                    $email = $string;
                }

                // Do we have a valid email?
                $email = sanitize_email($email);

                if (empty($email)) {
                    continue;
                }

                // Add the email as a term if they don't exist
                $term = $this->add_term_if_not_exists($string, $this->notify_email_taxonomy);

                if (! is_wp_error($term)) {
                    $email_terms[] = $string;
                }
            }

            $set = wp_set_object_terms($post->ID, $email_terms, $this->notify_email_taxonomy, $append);

            if (is_wp_error($set)) {
                return $set;
            } else {
                return true;
            }
        }

        /**
         * Removes user from pp_notify_user taxonomy for the given Post,
         * so they no longer receive future notifications.
         *
         * @param object $post Post object or ID
         * @param int|string|array $users One or more users to stop being notified for the post
         *
         * @return true|WP_Error     $response  True on success, WP_Error on failure
         */
        private function post_set_users_stop_notify($post, $users)
        {
            $post = get_post($post);
            if (! $post) {
                return new WP_Error('missing-post', $this->module->messages['missing-post']);
            }

            if (! is_array($users)) {
                $users = [$users];
            }

            $terms = get_the_terms($post->ID, $this->notify_user_taxonomy);
            if (is_wp_error($terms)) {
                return $terms;
            }

            $user_terms = wp_list_pluck($terms, 'slug');
            foreach ($users as $user) {
                if (is_int($user)) {
                    $user = get_user_by('id', $user);
                } elseif (is_string($user)) {
                    $user = get_user_by('login', $user);
                }

                if (! is_object($user)) {
                    continue;
                }

                $key = array_search($user->user_login, $user_terms);
                if (false !== $key) {
                    unset($user_terms[$key]);
                }
            }
            $set = wp_set_object_terms($post->ID, $user_terms, $this->notify_user_taxonomy, false);

            if (is_wp_error($set)) {
                return $set;
            } else {
                return true;
            }
        }

        /**
         * Removes users that are deleted from receiving future notifications (i.e. makes them out of notify list for posts FOREVER! )
         *
         * @param $id int ID of the user
         */
        public function delete_user_action($id)
        {
            if (! $id) {
                return;
            }

            // get user data
            $user = get_userdata($id);

            if ($user) {
                // Delete term from the pp_notify_user taxonomy
                $notify_user_term = get_term_by('name', $user->user_login, $this->notify_user_taxonomy);
                if ($notify_user_term) {
                    wp_delete_term($notify_user_term->term_id, $this->notify_user_taxonomy);
                }
            }

            return;
        }

        /**
         * Add user as a term if they aren't already
         *
         * @param $term     string term to be added
         * @param $taxonomy string taxonomy to add term to
         *
         * @return WP_error if insert fails, true otherwise
         */
        private function add_term_if_not_exists($term, $taxonomy, $args = [])
        {
            if (! term_exists($term, $taxonomy)) {
                if (empty($args)) {
                    $args = ['slug' => sanitize_title($term)];
                }

                return wp_insert_term($term, $taxonomy, $args);
            }

            return true;
        }

        /**
         * Gets a list of the users to be notified for the specified post
         *
         * @param int $post_id The ID of the post
         * @param string $return The field to return
         *
         * @return array $users Users to notify for the specified posts
         */
        public function get_users_to_notify($post_id, $return = 'user_login')
        {
            // Get pp_notify_user terms for the post
            $users = wp_get_object_terms($post_id, $this->notify_user_taxonomy, ['fields' => 'names']);

            // Don't have any users to notify
            if (! $users || is_wp_error($users)) {
                return [];
            }

            // if just want user_login, return as is
            if ($return == 'user_login') {
                return $users;
            }

            foreach ((array )$users as $key => $user) {
                switch ($user) {
                    case is_int($user):
                        $search = 'id';
                        break;
                    case is_email($user):
                        $search = 'email';
                        break;
                    default:
                        $search = 'login';
                        break;
                }
                $new_user = get_user_by($search, $user);
                if (! $new_user || ! is_user_member_of_blog($new_user->ID)) {
                    unset($users[$key]);
                    continue;
                }
                switch ($return) {
                    case 'user_login':
                        $users[$key] = $new_user->user_login;
                        break;
                    case 'id':
                        $users[$key] = $new_user->ID;
                        break;
                    case 'user_email':
                        $users[$key] = $new_user->user_email;
                        break;
                    case 'object':
                        $users[$key] = $new_user;
                        break;
                }
            }
            if (! $users || is_wp_error($users)) {
                $users = [];
            }

            return $users;
        }

        /**
         * Gets a list of the emails that should be notified for the specified post
         *
         * @param int $post_id
         *
         * @return array $roles All of the role slugs
         */
        public function get_emails_to_notify($post_id)
        {
            $emails = wp_get_object_terms($post_id, $this->notify_email_taxonomy);

            $list = [];
            if (! empty($emails)) {
                foreach ($emails as $email) {
                    $list[] = $email->name;
                }
            }

            return $list;
        }

        /**
         * Gets a list of the roles that should be notified for the specified post
         *
         * @param int $post_id
         * @param string $return
         *
         * @return array $roles All of the role slugs
         */
        public function get_roles_to_notify($post_id, $return = 'all')
        {
            // Workaround for the fact that get_object_terms doesn't return just slugs
            if ($return == 'slugs') {
                $fields = 'all';
            } else {
                $fields = $return;
            }

            $roles = wp_get_object_terms($post_id, $this->notify_role_taxonomy, ['fields' => $fields]);

            if ($return == 'slugs') {
                $slugs = [];
                foreach ($roles as $role) {
                    $slugs[] = $role->slug;
                }
                $roles = $slugs;
            }

            return $roles;
        }

        /**
         * Gets a list of the groups that should be notified for the specified post
         *
         * @param int $post_id
         * @param string $return
         *
         * @return array $groups All of the role slugs
         */
        public function get_groups_to_notify($post_id, $return = 'all')
        {
            // Workaround for the fact that get_object_terms doesn't return just slugs
            $fields = ($return == 'slugs') ? 'all' : $return;

            $group_terms = wp_get_object_terms($post_id, $this->notify_group_taxonomy, compact('fields'));

            if (!is_array($group_terms)) {
                $group_terms = [];
            } else {
                if ('slugs' == $return) {
                    $group_terms = array_column($group_terms, 'slug');
                }
            }

            return $group_terms;
        }

        /**
         * Gets a list of posts that a user is selected to be notified
         *
         * @param string|int $user user_login or id of user
         * @param array $args
         *
         * @return array $posts Posts a user is selected to be notified
         */
        public function get_user_to_notify_posts($user = 0, $args = null)
        {
            if (! $user) {
                $user = (int )wp_get_current_user()->ID;
            }

            if (is_int($user)) {
                $user = get_userdata($user)->user_login;
            }

            $post_args = [
                'tax_query' => [
                    [
                        'taxonomy' => $this->notify_user_taxonomy,
                        'field' => 'slug',
                        'terms' => $user,
                    ],
                ],
                'posts_per_page' => '10',
                'orderby' => 'modified',
                'order' => 'DESC',
                'post_status' => 'any',
            ];
            $post_args = apply_filters('pp_user_to_notify_posts_query_args', $post_args);
            $posts = get_posts($post_args);

            return $posts;
        }

        /**
         * Register settings for notifications so we can partially use the Settings API
         * (We use the Settings API for form generation, but not saving )
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
                __('Show the "Notify me" and "Stop notifying me" links for these post types:', 'publishpress'),
                [$this, 'settings_post_types_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'email_from',
                __('Email from:', 'publishpress'),
                [$this, 'settings_email_from_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'notify_author_by_default',
                __('Always notify the author of the content:', 'publishpress'),
                [$this, 'settings_notify_author_by_default_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'notify_current_user_by_default',
                __('Always notify users who have edited the content:', 'publishpress'),
                [$this, 'settings_notify_current_user_by_default_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'blacklisted_taxonomies',
                __('Blacklisted taxonomies for Notifications', 'publishpress'),
                [$this, 'settings_blacklisted_taxonomies_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );
        }

        /**
         * Chose the post types for notifications
         *
         * @since 0.7
         */
        public function settings_post_types_option()
        {
            global $publishpress;
            $publishpress->settings->helper_option_custom_post_type($this->module);
        }

        public function get_email_from()
        {
            if (! isset($this->module->options->email_from_name)) {
                $name = get_bloginfo('name');
            } else {
                $name = $this->module->options->email_from_name;
            }

            if (! isset($this->module->options->email_from)) {
                $email = get_bloginfo('admin_email');
            } else {
                $email = $this->module->options->email_from;
            }

            return [
                'name' => $name,
                'email' => $email,
            ];
        }

        /**
         * Field to customize the email for the "email from".
         */
        public function settings_email_from_option()
        {
            $email_from = $this->get_email_from();

            echo '<input
                    id="' . esc_attr($this->module->slug) . '_email_from_name"
                    type="text"
                    style="min-width: 300px"
                    placeholder="' . esc_attr(get_bloginfo('name')) . '"
                    name="' . esc_attr($this->module->options_group_name) . '[email_from_name]"
                    value="' . esc_attr($email_from['name']) . '" />
                </label>';
            echo '<br />';
            echo '<input
                    id="' . esc_attr($this->module->slug) . '_email_from"
                    type="email"
                    style="min-width: 300px"
                    placeholder="' . esc_attr(get_bloginfo('admin_email')) . '"
                    name="' . esc_attr($this->module->options_group_name) . '[email_from]"
                    value="' . esc_attr($email_from['email']) . '" />
                </label>';
        }

        /**
         * Field to choose between auto select author for notifications.
         */
        public function settings_notify_author_by_default_option()
        {
            $checked = '1';
            if (isset($this->module->options->notify_author_by_default)) {
                $checked = $this->module->options->notify_author_by_default;
            }

            echo '<input
                    id="' . esc_attr($this->module->slug) . '_notify_author_by_default"
                    type="checkbox"
                    name="' . esc_attr($this->module->options_group_name) . '[notify_author_by_default]"
                    value="1" ' . ((bool)$checked ? 'checked="checked"' : '') . '/>';
        }

        /**
         * Field to choose between auto select current user for notifications.
         */
        public function settings_notify_current_user_by_default_option()
        {
            $checked = '1';
            if (isset($this->module->options->notify_current_user_by_default)) {
                $checked = $this->module->options->notify_current_user_by_default;
            }

            echo '<input
                    id="' . esc_attr($this->module->slug) . '_notify_current_user_by_default"
                    type="checkbox"
                    name="' . esc_attr($this->module->options_group_name) . '[notify_current_user_by_default]"
                    value="1" ' . ((bool)$checked ? 'checked="checked"' : '') . '/>';
        }

        public function settings_blacklisted_taxonomies_option()
        {
            $blacklisted_taxonomies = isset($this->module->options->blacklisted_taxonomies)
                ? $this->module->options->blacklisted_taxonomies
                : '';
            ?>
            <div style="max-width: 300px;">
                <input
                        type="text"
                        id="<?php
                        echo esc_attr($this->module->slug); ?>_blacklisted_taxonomies"
                        name="<?php
                        echo esc_attr($this->module->options_group_name); ?>[blacklisted_taxonomies]"
                        value="<?php
                        echo esc_attr($blacklisted_taxonomies); ?>"
                        placeholder="<?php
                        esc_html_e('slug1,slug2', 'publishpress'); ?>"
                        style="width: 100%;"
                />

                <div style="margin-top: 5px;">
                    <p><?php
                        esc_html_e(
                            'Add a list of taxonomy-slugs separated by comma that should not be loaded by the Taxonomy content filter when adding a new Notification Workflow.',
                            'publishpress'
                        ); ?></p>
                </div>
            </div>
            <?php
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

            if (isset($new_options['email_from'])) {
                $new_options['email_from_name'] = filter_var($new_options['email_from_name'], FILTER_SANITIZE_STRING);
                $new_options['email_from'] = filter_var($new_options['email_from'], FILTER_SANITIZE_EMAIL);
            }


            if (isset($new_options['notify_author_by_default'])) {
                $new_options['notify_author_by_default'] = (bool)$new_options['notify_author_by_default'] ? '1' : '0';
            } else {
                $new_options['notify_author_by_default'] = '0';
            }


            if (isset($new_options['notify_current_user_by_default'])) {
                $new_options['notify_current_user_by_default'] = (bool)$new_options['notify_current_user_by_default'] ? '1' : '0';
            } else {
                $new_options['notify_current_user_by_default'] = '0';
            }

            return $new_options;
        }

        /**
         * Settings page for notifications
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
                foreach ($publishpress->class_names as $slug => $class_name) {
                    $mod_data = $publishpress->$slug->module;

                    if ($mod_data->autoload
                        || $mod_data->slug === $this->module->slug
                        || ! isset($mod_data->notification_options)
                        || $mod_data->options->enabled != 'on') {
                        continue;
                    }

                    echo '<input name="publishpress_module_name[]" type="hidden" value="' . esc_attr(
                            $mod_data->name
                        ) . '" />';

                    $publishpress->$slug->print_configure_view();
                } ?>

                <?php
                echo '<input name="publishpress_module_name[]" type="hidden" value="' . esc_attr($this->module->name) . '" />'; ?>
                <?php
                wp_nonce_field('edit-publishpress-settings');

                submit_button(null, 'primary', 'submit', false); ?>
            </form>
            <?php
        }

        /**
         * Gets a simple phrase containing the formatted date and time that the post is scheduled for.
         *
         * @param obj $post Post object
         *
         * @return str    $scheduled_datetime The scheduled datetime in human-readable format
         * @since 0.8
         *
         */
        private function get_scheduled_datetime($post)
        {
            $scheduled_ts = strtotime($post->post_date);

            $date = date_i18n(get_option('date_format'), $scheduled_ts);
            $time = date_i18n(get_option('time_format'), $scheduled_ts);

            return sprintf(__('%1$s at %2$s', 'publishpress'), $date, $time);
        }

        public function send_notification_status_update($args)
        {
            $post = $args['post'];
            $new_status = apply_filters('publishpress_notifications_status', $args['new_status'], $post);
            $old_status = apply_filters('publishpress_notifications_status', $args['old_status'], $post);

            // Get current user
            $current_user = wp_get_current_user();

            // Get current user
            $current_user = wp_get_current_user();

            $post_author = get_userdata($post->post_author);

            $blogname = get_bloginfo('name');

            $body = '';

            $post_id = $post->ID;
            $post_title = pp_draft_or_post_title($post_id);
            $post_type = get_post_type_object($post->post_type)->labels->singular_name;

            if (0 != $current_user->ID) {
                $current_user_display_name = $current_user->display_name;
                $current_user_email = sprintf('(%s )', $current_user->user_email);
            } else {
                $current_user_display_name = __('WordPress Scheduler', 'publishpress');
                $current_user_email = '';
            }

            // Email subject and first line of body
            // Set message subjects according to what action is being taken on the Post
            if ($old_status == 'new' || $old_status == 'auto-draft') {
                /* translators: 1: site name, 2: post type, 3. post title */
                $subject = sprintf(
                    __('[%1$s] New %2$s Created: "%3$s"', 'publishpress'),
                    $blogname,
                    $post_type,
                    $post_title
                );
                /* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
                $body .= sprintf(
                        __('A new %1$s (#%2$s "%3$s" ) was created by %4$s %5$s', 'publishpress'),
                        $post_type,
                        $post_id,
                        $post_title,
                        $current_user->display_name,
                        $current_user->user_email
                    ) . "\r\n";
            } elseif ($new_status == 'trash') {
                /* translators: 1: site name, 2: post type, 3. post title */
                $subject = sprintf(
                    __('[%1$s] %2$s Trashed: "%3$s"', 'publishpress'),
                    $blogname,
                    $post_type,
                    $post_title
                );
                /* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
                $body .= sprintf(
                        __('%1$s #%2$s "%3$s" was moved to the trash by %4$s %5$s', 'publishpress'),
                        $post_type,
                        $post_id,
                        $post_title,
                        $current_user_display_name,
                        $current_user_email
                    ) . "\r\n";
            } elseif ($old_status == 'trash') {
                /* translators: 1: site name, 2: post type, 3. post title */
                $subject = sprintf(
                    __('[%1$s] %2$s Restored (from Trash ): "%3$s"', 'publishpress'),
                    $blogname,
                    $post_type,
                    $post_title
                );
                /* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
                $body .= sprintf(
                        __('%1$s #%2$s "%3$s" was restored from trash by %4$s %5$s', 'publishpress'),
                        $post_type,
                        $post_id,
                        $post_title,
                        $current_user_display_name,
                        $current_user_email
                    ) . "\r\n";
            } elseif ($new_status == 'future') {
                /* translators: 1: site name, 2: post type, 3. post title */
                $subject = sprintf(__('[%1$s] %2$s Scheduled: "%3$s"'), $blogname, $post_type, $post_title);
                /* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email 6. scheduled date  */
                $body .= sprintf(
                        __('%1$s #%2$s "%3$s" was scheduled by %4$s %5$s.  It will be published on %6$s'),
                        $post_type,
                        $post_id,
                        $post_title,
                        $current_user_display_name,
                        $current_user_email,
                        $this->get_scheduled_datetime($post)
                    ) . "\r\n";
            } elseif ($new_status == 'publish') {
                /* translators: 1: site name, 2: post type, 3. post title */
                $subject = sprintf(
                    __('[%1$s] %2$s Published: "%3$s"', 'publishpress'),
                    $blogname,
                    $post_type,
                    $post_title
                );
                /* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
                $body .= sprintf(
                        __('%1$s #%2$s "%3$s" was published by %4$s %5$s', 'publishpress'),
                        $post_type,
                        $post_id,
                        $post_title,
                        $current_user_display_name,
                        $current_user_email
                    ) . "\r\n";
            } elseif ($old_status == 'publish') {
                /* translators: 1: site name, 2: post type, 3. post title */
                $subject = sprintf(
                    __('[%1$s] %2$s Unpublished: "%3$s"', 'publishpress'),
                    $blogname,
                    $post_type,
                    $post_title
                );
                /* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
                $body .= sprintf(
                        __('%1$s #%2$s "%3$s" was unpublished by %4$s %5$s', 'publishpress'),
                        $post_type,
                        $post_id,
                        $post_title,
                        $current_user_display_name,
                        $current_user_email
                    ) . "\r\n";
            } else {
                /* translators: 1: site name, 2: post type, 3. post title */
                $subject = sprintf(
                    __('[%1$s] %2$s Status Changed for "%3$s"', 'publishpress'),
                    $blogname,
                    $post_type,
                    $post_title
                );
                /* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
                $body .= sprintf(
                        __('Status was changed for %1$s #%2$s "%3$s" by %4$s %5$s', 'publishpress'),
                        $post_type,
                        $post_id,
                        $post_title,
                        $current_user_display_name,
                        $current_user_email
                    ) . "\r\n";
            }

            /* translators: 1: date, 2: time, 3: timezone */
            $body .= sprintf(
                    __('This action was taken on %1$s at %2$s %3$s', 'publishpress'),
                    date_i18n(get_option('date_format')),
                    date_i18n(get_option('time_format')),
                    get_option('timezone_string')
                ) . "\r\n";

            $old_status_friendly_name = $this->get_post_status_friendly_name($old_status);
            $new_status_friendly_name = $this->get_post_status_friendly_name($new_status);

            // Email body
            $body .= "\r\n";
            /* translators: 1: old status, 2: new status */
            $body .= sprintf(
                __('%1$s => %2$s', 'publishpress'),
                $old_status_friendly_name,
                $new_status_friendly_name
            );
            $body .= "\r\n\r\n";

            $body .= "--------------------\r\n\r\n";

            $body .= sprintf(__('== %s Details ==', 'publishpress'), $post_type) . "\r\n";
            $body .= sprintf(__('Title: %s', 'publishpress'), $post_title) . "\r\n";
            if (! empty($post_author)) {
                /* translators: 1: author name, 2: author email */
                $body .= sprintf(
                        __('Author: %1$s (%2$s )', 'publishpress'),
                        $post_author->display_name,
                        $post_author->user_email
                    ) . "\r\n";
            }

            $admin_path = 'post.php?post=' . $post_id . '&action=edit';
            $edit_link = htmlspecialchars_decode(admin_url($admin_path));
            if ($new_status != 'publish') {
                $view_link = add_query_arg(['preview' => 'true'], wp_get_shortlink($post_id));
            } else {
                $view_link = htmlspecialchars_decode(get_permalink($post_id));
            }
            $body .= "\r\n";
            $body .= __('== Actions ==', 'publishpress') . "\r\n";
            $body .= sprintf(
                    __('Add editorial comment: %s', 'publishpress'),
                    $edit_link . '#editorialcomments/add'
                ) . "\r\n";
            $body .= sprintf(__('Edit: %s', 'publishpress'), $edit_link) . "\r\n";
            $body .= sprintf(__('View: %s', 'publishpress'), $view_link) . "\r\n";

            $body .= $this->get_notification_footer($post);

            $this->send_email('status-change', $post, $subject, $body);
        }

        public function send_notification_comment($args)
        {
            /* translators: 1: blog name, 2: post title */
            $subject = sprintf(
                __('[%1$s] New Editorial Comment: "%2$s"', 'publishpress'),
                $args['blogname'],
                $args['post_title']
            );

            /* translators: 1: post id, 2: post title, 3. post type */
            $body = sprintf(
                    __('A new editorial comment was added to %3$s #%1$s "%2$s"', 'publishpress'),
                    $args['post_id'],
                    $args['post_title'],
                    $args['post_type']
                ) . "\r\n\r\n";
            /* translators: 1: comment author, 2: author email, 3: date, 4: time */
            $body .= sprintf(
                    __('%1$s (%2$s ) said on %3$s at %4$s:', 'publishpress'),
                    $args['current_user']->display_name,
                    $args['current_user']->user_email,
                    mysql2date(get_option('date_format'), $args['comment']->comment_date),
                    mysql2date(get_option('time_format'), $args['comment']->comment_date)
                ) . "\r\n";
            $body .= "\r\n" . $args['comment']->comment_content . "\r\n";

            // @TODO: mention if it was a reply
            /*
            if( $parent ) {

            }
            */

            $body .= "\r\n--------------------\r\n";

            $admin_path = 'post.php?post=' . $args['post_id'] . '&action=edit';
            $edit_link = htmlspecialchars_decode(admin_url($admin_path));
            $view_link = htmlspecialchars_decode(get_permalink($args['post_id']));

            $body .= "\r\n";
            $body .= __('== Actions ==', 'publishpress') . "\r\n";
            $body .= sprintf(
                    __('Reply: %s', 'publishpress'),
                    $edit_link . '#editorialcomments/reply/' . $args['comment']->comment_ID
                ) . "\r\n";
            $body .= sprintf(
                    __('Add editorial comment: %s', 'publishpress'),
                    $edit_link . '#editorialcomments/add'
                ) . "\r\n";
            $body .= sprintf(__('Edit: %s', 'publishpress'), $edit_link) . "\r\n";
            $body .= sprintf(__('View: %s', 'publishpress'), $view_link) . "\r\n";

            $body .= "\r\n" . sprintf(
                    __('You can see all editorial comments on this %s here: ', 'publishpress'),
                    $args['post_type']
                ) . "\r\n";
            $body .= $edit_link . "#editorialcomments" . "\r\n\r\n";

            $body .= $this->get_notification_footer($args['post']);

            //attach files to email
            $attachments = [];
            $comment_files = get_comment_meta($args['comment']->comment_ID, '_pp_editorial_comment_files', true);
            if (!empty($comment_files)) {
                $comment_files = explode(" ", $comment_files);
                $comment_files = array_filter($comment_files);
                foreach ($comment_files as $comment_file_id) {
                    $media_file = wp_get_attachment_url($comment_file_id);
                    if (!is_wp_error($media_file) && !empty($media_file)) {
                        $attachments[] = $media_file;
                    }
                }
            }

            $this->send_email('comment', $args['post'], $subject, $body, '', null, $attachments);
        }

        public static function getOption($option_name)
        {
            $is_module_enabled = PP_Module::isPublishPressModuleEnabled(self::MODULE_NAME);
            if (! $is_module_enabled) {
                return null;
            }

            global $publishpress;

            $module_options = $publishpress->{self::MODULE_NAME}->module->options;
            if (! isset($module_options->{$option_name})) {
                return null;
            }

            return $module_options->{$option_name};
        }
    }
}
