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

use PublishPress\Notifications\Helper;
use PublishPress\Notifications\Traits\Dependency_Injector;
use PublishPress\Notifications\Traits\PublishPress_Module;
use PublishPress\Notifications\Workflow\Step\Action\Notification as Notification;
use PublishPress\Notifications\Workflow\Step\Content\Main as Content_Main;
use PublishPress\Notifications\Workflow\Step\Event\Editorial_Comment as Event_Editorial_Comment;
use PublishPress\Notifications\Workflow\Step\Event\Filter\Post_Status as Filter_Post_Status;
use PublishPress\Notifications\Workflow\Step\Event\Post_StatusTransition;
use PublishPress\Notifications\Workflow\Step\Event\Post_TaxonomyUpdate;
use PublishPress\Notifications\Workflow\Step\Event\Post_Update;
use PublishPress\Notifications\Workflow\Step\Event_Content\Filter\Post_Type as Post_Type_Filter;
use PublishPress\Notifications\Workflow\Step\Event_Content\Post_Type;
use PublishPress\Notifications\Workflow\Step\Receiver\Site_Admin as Receiver_Site_Admin;
use PublishPress\NotificationsLog\NotificationsLogHandler;

if (! class_exists('PP_Improved_Notifications')) {
    /**
     * class Notifications
     */
    #[\AllowDynamicProperties]
    class PP_Improved_Notifications extends PP_Module
    {
        use Dependency_Injector, PublishPress_Module;

        const SETTINGS_SLUG = 'pp-improved-notifications-settings';

        const META_KEY_IS_DEFAULT_WORKFLOW = '_psppno_is_default_workflow';

        public $module_name = 'improved-notifications';

        /**
         * Instance for the module
         *
         * @var stdClass
         */
        public $module;

        /**
         * List of workflows
         *
         * @var array
         */
        protected $workflows;

        /**
         * List of published workflows
         *
         * @var array
         */
        protected $published_workflows;

        /**
         * Default workflows which are currently stored
         *
         * @var array
         */
        protected $stored_defaults;

        /**
         * Construct the Notifications class
         */
        public function __construct()
        {
            global $publishpress;

            $this->viewsPath = dirname(dirname(dirname(__FILE__))) . '/views';

            $this->module_url = $this->get_module_url(__FILE__);

            // Register the module with PublishPress
            $args = [
                'title' => __('Advanced Notifications', 'publishpress'),
                'short_description' => false,
                'extended_description' => false,
                'module_url' => $this->module_url,
                'icon_class' => 'dashicons dashicons-feedback',
                'slug' => 'improved-notifications',
                'default_options' => [
                    'enabled' => 'on',
                    'post_types' => ['post'],
                    'default_channels' => apply_filters('psppno_filter_default_notification_channel', 'email'),
                    'duplicated_notification_threshold' => Notification::DEFAULT_DUPLICATED_NOTIFICATION_THRESHOLD_IN_MINUTES,
                ],
                'notification_options' => true,
                'autoload' => false,
                'skip_settings' => true,
            ];

            // Apply a filter to the default options
            $args['default_options'] = apply_filters('publishpress_notif_default_options', $args['default_options']);
            $this->module = $publishpress->register_module(
                PublishPress\Legacy\Util::sanitize_module_name($this->module_name),
                $args
            );

            parent::__construct();
        }

        /**
         * Initialize the module. Conditionally loads if the module is enabled
         */
        public function init()
        {
            if (is_admin()) {
                add_action('admin_enqueue_scripts', [$this, 'add_admin_scripts']);

                add_action('admin_init', [$this, 'register_settings']);

                // Workflow form
                add_filter('get_sample_permalink_html', [$this, 'filter_get_sample_permalink_html_workflow'], 9, 5);
                add_filter('post_row_actions', [$this, 'filter_row_actions'], 10, 2);
                add_action(
                    'add_meta_boxes_' . PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW,
                    [$this, 'action_meta_boxes_workflow']
                );

                // Add fields to the user's profile screen to select notification channels
                add_action('show_user_profile', [$this, 'user_profile_fields']);
                add_action('edit_user_profile', [$this, 'user_profile_fields']);
                // Add action to save data from the user's profile screen
                add_action('personal_options_update', [$this, 'save_user_profile_fields']);
                add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);
                // Load CSS
                add_action('admin_print_styles', [$this, 'add_admin_styles']);

                // Inject the PublishPress footer
                add_filter('admin_footer_text', [$this, 'update_footer_admin']);
            }

            add_action('save_post', [$this, 'save_meta_boxes'], 10, 2);

            // Cancel the PublishPress and PublishPress Slack Notifications, since they will be sent by the cron task.
            add_filter('publishpress_slack_enable_notifications', [$this, 'filter_slack_enable_notifications']);
            remove_all_actions('pp_send_notification_status_update');
            remove_all_actions('pp_send_notification_comment');


            // Add action to intercept transition between post status - post save
            add_action('wp_after_insert_post', [$this, 'action_transition_post_status'], 999, 4);
            add_action('wp_after_insert_post', [$this, 'action_update_post'], 995, 4);

            // Add action to intercep new editorial comments
            add_action('pp_post_insert_editorial_comment', [$this, 'action_editorial_comment'], 999, 3);

            // Add action to intercep taxonomy term update
            add_action('set_object_terms', [$this, 'action_post_taxonomy_update'], 999, 4);


            add_filter(
                'pp_notification_send_email_message_headers',
                [$this, 'filter_send_email_message_headers'],
                10,
                3
            );

            add_action('pp_init', [$this, 'action_after_init']);

            add_filter('psppno_default_channel', [$this, 'filter_default_channel'], 10, 2);
            // Preventing sending async notification if event args is invalid
            add_filter('publishpress_notifications_stop_sync_notifications', [$this, 'should_stop_notification'], 10,2);
            // Prevent notification schedule if even args is not array
            add_filter('publishpress_notifications_schedule_notification', [$this, 'should_schedule_notification'], 10,2);
        }

        /**
         * Preventing sending async notification if event args is invalid
         * @param bool $stop
         * @param array $workflow
         * 
         * @return bool
         */
        public function should_stop_notification($stop, $workflow) {
            if (!is_object($workflow) || empty($workflow->event_args) || !is_array($workflow->event_args)) {
                $stop = true;
                return $stop;
            }
            
            return $stop;
        }
        
        /**
         * Prevent notification schedule if even args is not array
         * @param bool $schedule
         * @param array $workflow
         * 
         * @return bool
         */
        public function should_schedule_notification($schedule, $workflow) {
            if (!is_object($workflow) || empty($workflow->event_args) || !is_array($workflow->event_args)) {
                $schedule = false;
                return $schedule;
            }
            
            return $schedule;
        }

        /**
         * Methods called after all modules where loaded and initialized,
         * to make sure all hooks are set before we start some specific features.
         */
        public function action_after_init()
        {
            // Instantiate the controller of workflow's
            $workflows_controller = $this->get_service('workflows_controller');
            $workflows_controller->load_workflow_steps();

            do_action('publishpress_workflow_steps_loaded');
        }

        /**
         * Register settings for notifications so we can partially use the Settings API
         * (We use the Settings API for form generation, but not saving )
         *
         * @since 1.18.1-beta.1
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
                'duplicate_notification_threshold',
                __('Duplicated notification threshold:', 'publishpress'),
                [$this, 'settings_duplicated_notification_threshold_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'default_channels',
                __('Default notification channels:', 'publishpress'),
                [$this, 'settings_default_channels_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );
        }

        /**
         * Settings page for notifications
         *
         * @since 1.18.1-beta.1
         */
        public function print_configure_view()
        {
            settings_fields($this->module->options_group_name);
            do_settings_sections($this->module->options_group_name);
        }

        public function get_default_workflow_storage() {
            global $wpdb;

            $query_args = [
                'post_type' => 'psppnotif_workflow',
                'meta_query' => [
                    [
                        'key' => '_psppno_is_default_workflow',
                        'value' => '1',
                    ]
                ],
            ];

            $stored_defaults = [];

            $query = new \WP_Query($query_args);

            foreach ($query->posts as $row) {
                if ('trash' != $row->post_status) {
                    $stored_defaults[$row->post_name] = $row->ID;
                }
            }

            return $stored_defaults;
        }

        protected function notification_workflow_exists($workflow_name, $args = []) {
            if (!empty($args['force_refresh']) || !isset($this->stored_defaults)) {
                $this->stored_defaults = $this->get_default_workflow_storage();
            }

            if (empty($this->stored_defaults) || !is_array($this->stored_defaults)) {
                return false;
            }

            $exists = false;

            foreach ($this->stored_defaults as $post_name => $post_id) {
                if ($post_name == $workflow_name) {
                    $exists = true;
                    break;

                } else {
                    if (!empty($args['check_default_workflow_name'])) {
                        $default_workflow_name = get_post_meta($post_id, '_psp_default_workflow_name', true);
                    } else {
                        $default_workflow_name = '';
                    }

                    if ($default_workflow_name && is_string($default_workflow_name)) {
                        if ($args['check_default_workflow_name'] == $default_workflow_name) {
                            $exists = true;
                            break;
                        }

                    // Match based on substring only if we don't have default name stored to postmeta
                    } elseif (0 === strpos($post_name, $workflow_name)) {
                        $exists = true;
                        break;
                    }
                }
            }

            return $exists;
        }

        /**
         * Create default notification workflows based on current notification settings
         */
        public function create_default_workflow_post_update()
        {
            if ($this->notification_workflow_exists('existing-post-is-updated')) {
                return;
            }

            $view = $this->get_service('view');

            // Post Save
            $workflow = [
                'post_status' => 'publish',
                'post_title' => __('Existing Post is updated', 'publishpress'),
                'post_type' => 'psppnotif_workflow',
                'meta_input' => [
                    static::META_KEY_IS_DEFAULT_WORKFLOW => '1',
                    Post_Update::META_KEY_SELECTED => '1',
                    Content_Main::META_KEY_SUBJECT => '&quot;[psppno_post title]&quot; was updated',
                    Content_Main::META_KEY_BODY => $view->render('workflow_default_content_post_update'),
                    Receiver_Site_Admin::META_KEY => 1,
                    Post_Type_Filter::META_KEY_POST_TYPE => 'post',
                    Post_Type::META_KEY_SELECTED => 1,
                ],
            ];

            $post_id = wp_insert_post($workflow);

            if (is_int($post_id) && ! empty($post_id)) {
                // Get post statuses
                $statuses = $this->get_post_statuses();
                // Add each status to the "From" and "To" filter
                foreach ($statuses as $status) {
                    add_post_meta($post_id, Filter_Post_Status::META_KEY_POST_STATUS_FROM, $status->slug, false);
                    add_post_meta($post_id, Filter_Post_Status::META_KEY_POST_STATUS_TO, $status->slug, false);
                }
            }
        }

        /**
         * Create default notification workflows based on current notification settings
         */
        public function create_default_workflow_new_draft_created()
        {
            if ($this->notification_workflow_exists('new-post-is-created-in-draft-status')) {
                return;
            }

            $view = $this->get_service('view');

            $statuses = [
                (object)[
                'slug' => 'auto-draft',
                ]
            ];

            // Post Save
            $workflow = [
                'post_status' => 'publish',
                'post_title' => __('New Post is created in Draft status', 'publishpress'),
                'post_type' => 'psppnotif_workflow',
                'meta_input' => [
                    static::META_KEY_IS_DEFAULT_WORKFLOW => '1',
                    Post_StatusTransition::META_KEY_SELECTED => '1',
                    Filter_Post_Status::META_KEY_POST_STATUS_TO => 'draft',
                    Content_Main::META_KEY_SUBJECT => '&quot;[psppno_post title]&quot; created in draft',
                    Content_Main::META_KEY_BODY => $view->render('workflow_default_content_new_draft_created'),
                    Receiver_Site_Admin::META_KEY => 1,
                    Post_Type_Filter::META_KEY_POST_TYPE => 'post',
                    Post_Type::META_KEY_SELECTED => 1,
                ],
            ];

            $post_id = wp_insert_post($workflow);

            if (is_int($post_id) && ! empty($post_id)) {
                // Add each status to the "From" filter, except the "publish" state
                foreach ($statuses as $status) {
                    add_post_meta($post_id, Filter_Post_Status::META_KEY_POST_STATUS_FROM, $status->slug, false);
                }
            }
        }

        /**
         * Create default notification workflows based on current notification settings
         */
        public function create_default_workflow_post_published()
        {
            if ($this->notification_workflow_exists('new-post-is-published')) {
                return;
            }

            $view = $this->get_service('view');

            // Get post statuses
            $statuses = $this->get_post_statuses();
            // Remove the published state
            foreach ($statuses as $index => $status) {
                if ($status->slug === 'publish') {
                    unset($statuses[$index]);
                }
            }
            $statuses[] = (object)[
                'slug' => 'new',
            ];
            $statuses[] = (object)[
                'slug' => 'auto-draft',
            ];

            // Post Published
            $workflow = [
                'post_status' => 'publish',
                'post_title' => __('New Post is Published', 'publishpress'),
                'post_type' => 'psppnotif_workflow',
                'meta_input' => [
                    static::META_KEY_IS_DEFAULT_WORKFLOW => '1',
                    Post_StatusTransition::META_KEY_SELECTED => '1',
                    Filter_Post_Status::META_KEY_POST_STATUS_TO => 'publish',
                    Content_Main::META_KEY_SUBJECT => '&quot;[psppno_post title]&quot; was published',
                    Content_Main::META_KEY_BODY => $view->render('workflow_default_content_post_published'),
                    Receiver_Site_Admin::META_KEY => 1,
                    Post_Type_Filter::META_KEY_POST_TYPE => 'post',
                    Post_Type::META_KEY_SELECTED => 1,
                ],
            ];
            

            $post_id = wp_insert_post($workflow);

            if (is_int($post_id) && ! empty($post_id)) {
                // Add each status to the "From" filter, except the "publish" state
                foreach ($statuses as $status) {
                    add_post_meta($post_id, Filter_Post_Status::META_KEY_POST_STATUS_FROM, $status->slug, false);
                }
            }
        }

        /**
         * Create default notification workflow for the editorial comments
         */
        public function create_default_workflow_editorial_comment()
        {
            if ($this->notification_workflow_exists('notify-on-editorial-comments')) {
                return;
            }
            
            $view = $this->get_service('view');

            // Post Save
            $workflow = [
                'post_status' => 'publish',
                'post_title' => __('Notify on editorial comments', 'publishpress'),
                'post_type' => 'psppnotif_workflow',
                'meta_input' => [
                    static::META_KEY_IS_DEFAULT_WORKFLOW => '1',
                    Event_Editorial_Comment::META_KEY_SELECTED => '1',
                    Content_Main::META_KEY_SUBJECT => 'New editorial comment to &quot;[psppno_post title]&quot;',
                    Content_Main::META_KEY_BODY => $view->render('workflow_default_content_editorial_comment'),
                    Receiver_Site_Admin::META_KEY => 1,
                    Post_Type_Filter::META_KEY_POST_TYPE => 'post',
                    Post_Type::META_KEY_SELECTED => 1,
                ],
            ];

            $post_id = wp_insert_post($workflow);

            if (is_int($post_id) && ! empty($post_id)) {
                // Get post statuses
                $statuses = $this->get_post_statuses();
                // Add each status to the "From" filter, except the "publish" state
                foreach ($statuses as $status) {
                    add_post_meta($post_id, Filter_Post_Status::META_KEY_POST_STATUS_FROM, $status->slug, false);
                    add_post_meta($post_id, Filter_Post_Status::META_KEY_POST_STATUS_TO, $status->slug, false);
                }
            }
        }

        /**
         * If any duplicate default notification workflows were erroneously created, move them to the trash.
         * 
         * This is called on version update from 4.7.1 or earlier due to the potential for duplication which was corrected in version 4.7.2
         */
        private function handle_duplicate_default_workflows() {
            global $wpdb;

            $trash_post_ids = [];

            $default_workflows = ['existing-post-is-updated', 'new-post-is-created-in-draft-status', 'new-post-is-published', 'notify-on-editorial-comments'];

            foreach ($default_workflows as $default_workflow_name) {
                // If duplicates exist, they will have the standard post_name numerically suffixed with "-#"
                // It is also possible that after duplication, the original workflow was manually deleted. In that case, subsequent duplicates will use the original post_name, then the next available "-#" suffix.
                // The decision was made to keep the oldest existing default workflow, even if it has a suffixed post_name. If this is not the copy the user wants deactivated, they can restore it from Trash.
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $posts = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT ID, post_name FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish' AND (post_name = %s OR post_name LIKE %s) ORDER BY ID ASC",
                        'psppnotif_workflow',
                        $default_workflow_name,
                        $wpdb->esc_like($default_workflow_name) . '-%'
                    )
                );

                // Query results are ordered by ID ascending, and we are keeping the first-created instance of each default Notification Workflow.
                $first_pass = true;

                foreach ($posts as $row) {
                    if (strlen($row->post_name) > $default_workflow_name) {
                        // Since the DB query's LIKE clause is not a regular expression ensuring numeric-only matches, disregard false matches now.
                        $str_suffix = substr($post->post_name, strlen($default_workflow_name) + 1);

                        if (!is_numeric($str_suffix)) {
                            continue;
                        }
                    }
                    
                    if ($first_pass) {
                        $first_pass = false;
                        continue;
                    }

                    $trash_post_ids []= $row->ID;
                }
            }

            if ($trash_post_ids) {
                $id_in_csv = implode("','", array_map('intval', $trash_post_ids));

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query("UPDATE $wpdb->posts SET post_status = 'trash' WHERE $wpdb->posts.post_type = 'psppnotif_workflow' AND $wpdb->posts.ID IN ('$id_in_csv')");    //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

                foreach ($trash_post_ids as $post_id) {
                    clean_post_cache($post_id);
                }
            }
        }

        public function filterSupportedModulesPostTypesArgs($args, $module)
        {
            if (isset($module->slug) && $module->slug === 'notifications') {
                $args = [];
            }

            return $args;
        }

        public function settings_duplicated_notification_threshold_option()
        {
            $value = Helper::getDuplicatedNotificationThreshold();

            echo '<input
                    id="' . esc_attr($this->module->slug) . '_duplicated_notification_threshold"
                    type="number"
                    min="1"
                    step="1"
                    name="' . esc_attr($this->module->options_group_name) . '[duplicated_notification_threshold]"
                    value="' . esc_attr($value) . '"/> ' . esc_html__('minutes', 'publishpress');
            ?>
            <div style="max-width: 300px;">
                <p><?php
                    esc_html_e(
                        'This will prevent too many notifications being sent for the same or similar events. Set this to a higher number if you are receiving duplicate emails.',
                        'publishpress'
                    ); ?>
                </p>
            </div>
            <?php
        }

        /**
         *
         */
        public function settings_default_channels_option()
        {
            $view = $this->get_service('view');

            /**
             * Filters the list of notification channels to display in the
             * user profile.
             *
             * [
             *    'name': string
             *    'label': string
             *    'options': [
             *        'name'
             *        'html'
             *    ]
             * ]
             *
             * @param array
             */
            $default_channels = [];
            $channels = apply_filters('psppno_filter_channels_user_profile', $default_channels);
            $default_channel = apply_filters('psppno_filter_default_notification_channel', 'email');
            $workflows = $this->get_published_workflows();
            $channels_options = isset($this->module->options->channel_options) ? (array)$this->module->options->channel_options : [];
            $selected_channels = isset($this->module->options->default_channels) ? (array)$this->module->options->default_channels : [];

            foreach ($workflows as $workflow) {
                if (! isset($selected_channels[$workflow->ID])) {
                    $selected_channels[$workflow->ID] = $default_channel;
                }
            }

            $context = [
                'labels' => [
                    'title' => esc_html__('Editorial Notifications', 'publishpress'),
                    'description' => esc_html__(
                        'Choose the channels where each workflow will send notifications to:',
                        'publishpress'
                    ),
                    'mute' => esc_html__('Muted', 'publishpress'),
                    'workflows' => esc_html__('Workflows', 'publishpress'),
                    'channels' => esc_html__('Channels', 'publishpress'),
                ],
                'workflows' => $workflows,
                'channels' => $channels,
                'selected_channels' => $selected_channels,
                'channels_options' => $channels_options,
            ];

            echo $this->get_service('view')->render('settings_notification_channels', $context);
        }

        /**
         * Returns true if we found any default workflow
         *
         * @return Bool
         */
        protected function has_default_workflows()
        {
            $query_args = [
                'post_type' => 'psppnotif_workflow',
                'meta_query' => [
                    [
                        'key' => static::META_KEY_IS_DEFAULT_WORKFLOW,
                        'value' => '1',
                    ],
                ],
            ];

            $query = new WP_Query($query_args);

            if (! $query->have_posts()) {
                return false;
            }

            // Ignore PublishPress Revisions Pro / Statuses Pro default Notification Workflows, which previously used meta_key '_psppno_is_default_workflow'
            $revisions_defaults = [
                'new-revision-created',
                'revision-is-submitted',
                'revision-is-scheduled',
                'revision-is-published',
                'revision-is-applied',
                'scheduled-revision-is-published',
                'revision-status-changed',
                'post-status-change',
                'post-declined',
                'revision-deferred-or-rejected',
            ];

            $any_planner_defaults = false;

            foreach ($query->posts as $k => $post) {
                foreach ($revisions_defaults as $default_post_name) {
                    if (0 === strpos($post->post_name, $default_post_name)) {
                        continue 2;
                    }
                }

                $any_planner_defaults = true;
                break;
            }

            return $any_planner_defaults;
        }

        /**
         * Load default editorial fields the first time the module is loaded
         *
         * @since 0.7
         */
        public function install()
        {
            if (false === $this->has_default_workflows()) {
                $this->create_default_workflow_post_update();
                $this->create_default_workflow_new_draft_created();
                $this->create_default_workflow_post_published();
            }
        }

        /**
         * Upgrade our data in case we need to
         *
         * @since 0.7
         */
        public function upgrade($previous_version)
        {
            if (version_compare($previous_version, '4.7.2-beta4', '<') && !defined('PUBLISHPRESS_NO_DUPLICATE_WORKFLOW_HANDLING')) {
                $this->handle_duplicate_default_workflows();
            }

            if (version_compare($previous_version, '1.8.1', '<=')) {
                // Upgrade settings _psppno_touser/_psppno_togroup to _psppno_touserlist/_psppno_togrouplist
                $workflows = $this->get_workflows();

                if (! empty($workflows)) {
                    foreach ($workflows as $workflow) {
                        // Get the user list
                        $meta = get_post_meta($workflow->ID, '_psppno_touser');
                        if (! empty($meta)) {
                            delete_post_meta($workflow->ID, '_psppno_touserlist');

                            foreach ($meta as $data) {
                                add_post_meta($workflow->ID, '_psppno_touserlist', $data);
                            }

                            delete_post_meta($workflow->ID, '_psppno_touser');
                            add_post_meta($workflow->ID, '_psppno_touser', 1);
                        }

                        // Get the user group list
                        $meta = get_post_meta($workflow->ID, '_psppno_togroup');
                        if (! empty($meta)) {
                            delete_post_meta($workflow->ID, '_psppno_togrouplist');

                            foreach ($meta as $data) {
                                add_post_meta($workflow->ID, '_psppno_togrouplist', $data);
                            }

                            delete_post_meta($workflow->ID, '_psppno_togroup');
                            add_post_meta($workflow->ID, '_psppno_togroup', 1);
                        }
                    }
                }
            }

            if (version_compare($previous_version, '1.10', '<=')) {
                $this->migrate_legacy_metadata_for_role();
            }

            if (version_compare($previous_version, '4.3.1', '<=')) {
                $this->create_default_workflow_post_update();
                $this->create_default_workflow_new_draft_created();
            }
        }

        protected function migrate_legacy_metadata_for_role()
        {
            global $wpdb;

            $wpdb->update(
                $wpdb->postmeta,
                ['meta_key' => '_psppno_torole'],
                ['meta_key' => '_psppno_togroup'],
                ['%s'],
                ['%s']
            );

            $wpdb->update(
                $wpdb->postmeta,
                ['meta_key' => '_psppno_torolelist'],
                ['meta_key' => '_psppno_togrouplist'],
                ['%s'],
                ['%s']
            );
        }


        /**
         * Filters the enable_notifications on the Slack add-on to block it.
         *
         * @param bool $enable_notifications
         *
         * @return bool
         */
        public function filter_slack_enable_notifications($enable_notifications)
        {
            return false;
        }

        /**
         * Action called on transitioning a post. Used to trigger the
         * controller of workflows to filter and execute them.
         *
         * @param int $postId
         * @param WP_Post $post
         * @param bool $update
         * @param null|WP_Post $postBefore
         * @return void
         */
        public function action_transition_post_status($postId, $post, $update, $postBefore)
        {
            if (! $this->is_supported_post_type($post->post_type) || !apply_filters('publishpress_notifications_do_transition_post_status', true, $post)) {
                return;
            }

            // Ignores auto-save
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            $oldStatus = 'new';
            if (is_object($postBefore)) {
                $oldStatus = apply_filters('publishpress_notifications_status', $postBefore->post_status, $postBefore);
            }

            $newStatus = apply_filters('publishpress_notifications_status', $post->post_status, $post);

            // Ignores if it is saved with the same status, avoiding multiple notifications on some situations.
            if ($oldStatus === $newStatus) {
                return;
            }

            // Go ahead and do the action to run workflows
            $params = [
                'event' => Post_StatusTransition::EVENT_NAME,
                'event_key' => Post_StatusTransition::META_KEY_SELECTED,
                'user_id' => get_current_user_id(),
                'params' => [
                    'post_id' => (int)$postId,
                    'new_status' => $newStatus,
                    'old_status' => $oldStatus,
                ],
            ];

            do_action('publishpress_notifications_trigger_workflows', $params);
        }

        /**
         * Action called on updating a post. Used to trigger the
         * controller of workflows to filter and execute them.
         *
         * @param int $postId
         * @param WP_Post $post
         * @param bool $update
         * @param null|WP_Post $postBefore
         * @return void
         *
         * @throws Exception
         */
        public function action_update_post($postId, $post, $update, $postBefore)
        {
            if (! $this->is_supported_post_type($post->post_type)) {
                return;
            }

            // Ignores auto-save
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            // Ignores trashed posts, we have the status transition event.
            if ('trash' === $post->post_status) {
                return;
            }

            $oldStatus = 'new';
            if (is_object($postBefore)) {
                $oldStatus = apply_filters('publishpress_notifications_status', $postBefore->post_status, $postBefore);
            }

            $newStatus = apply_filters('publishpress_notifications_status', $post->post_status, $post);

            // Go ahead and do the action to run workflows
            $params = [
                'event' => Post_Update::EVENT_NAME,
                'event_key' => Post_Update::META_KEY_SELECTED,
                'user_id' => get_current_user_id(),
                'params' => [
                    'post_id' => (int)$postId,
                    'new_status' => $newStatus,
                    'old_status' => $oldStatus,
                ],
            ];

            do_action('publishpress_notifications_trigger_workflows', $params);
        }

        /**
         * Action called on taxonomy term update. Used to trigger the
         * controller of workflows to filter and execute them.
         *
         * @param int $object_id
         * @param array $terms An array of object term IDs or slugs.
         * @param array $tt_ids An array of term taxonomy IDs.
         * @param string $taxonomy Taxonomy slug.
         * @return void
         *
         * @throws Exception
         */
        public function action_post_taxonomy_update($object_id, $terms, $tt_ids, $taxonomy)
        {
            // Go ahead and do the action to run workflows
            $post = get_post($object_id);

            if (!is_object($post) || !isset($post->post_type) || ! $this->is_supported_post_type($post->post_type)) {
                return;
            }

            $params = [
                'event' => Post_TaxonomyUpdate::EVENT_NAME,
                'event_key' => Post_TaxonomyUpdate::META_KEY_SELECTED,
                'user_id' => get_current_user_id(),
                'params' => [
                    'post_id'   => (int)$post->ID,
                    'terms'     => $terms,
                    'taxonomy'  => $taxonomy,
                ],
            ];

            do_action('publishpress_notifications_trigger_workflows', $params);
        }

        /**
         * Action called on editorial comments. Used to trigger the
         * controller of workflows to filter and execute them.
         *
         * @param WP_Comment $comment
         *
         * @throws Exception
         */
        public function action_editorial_comment($comment)
        {
            // Go ahead and do the action to run workflows
            $post = get_post($comment->comment_post_ID);

            if (! $this->is_supported_post_type($post->post_type)) {
                return;
            }

            $params = [
                'event' => 'editorial_comment',
                'event_key' => '_psppno_evtedcomment',
                'user_id' => get_current_user_id(),
                'params' => [
                    'post_id' => (int)$post->ID,
                    'comment_id' => (int)$comment->comment_ID,
                ],
            ];

            do_action('publishpress_notifications_trigger_workflows', $params);
        }

        /**
         * @param $post_type
         *
         * @return bool
         * @throws Exception
         */
        private function is_supported_post_type($post_type)
        {
            $publishpress = $this->get_service('publishpress');

            $supportedPostTypes = $publishpress->improved_notifications->get_all_post_types();

            return array_key_exists($post_type, $supportedPostTypes);
        }

        /**
         * Enqueue scripts and stylesheets for the admin pages.
         *
         * @param string $hook_suffix
         */
        public function add_admin_scripts($hook_suffix)
        {
            if (in_array($hook_suffix, ['profile.php', 'user-edit.php'])) {
                wp_enqueue_script(
                    'psppno-user-profile-notifications',
                    plugin_dir_url(__FILE__) . 'assets/js/user_profile.js',
                    [],
                    PUBLISHPRESS_VERSION
                );
            }

            if (in_array($hook_suffix, ['post.php', 'post-new.php'])) {
                if (PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW === get_post_type()) {
                    wp_enqueue_script(
                        'psppno-multiple-select',
                        plugin_dir_url(__FILE__) . 'assets/js/multiple-select.js',
                        ['jquery'],
                        PUBLISHPRESS_VERSION
                    );
                    wp_enqueue_script(
                        'psppno-workflow-tooltip',
                        plugin_dir_url(__FILE__) . 'libs/opentip/downloads/opentip-jquery.js',
                        ['jquery'],
                        PUBLISHPRESS_VERSION
                    );
                    wp_enqueue_script(
                        'psppno-workflow-form',
                        plugin_dir_url(__FILE__) . 'assets/js/workflow_form.js',
                        ['jquery', 'psppno-workflow-tooltip', 'psppno-multiple-select'],
                        PUBLISHPRESS_VERSION
                    );

                    wp_localize_script(
                        'psppno-workflow-form',
                        'workflowFormData',
                        [
                            'messages' => [
                                'selectAllIn_event' => 'Select at least one event.',
                                'selectAllIn_event_content' => 'Select at least a filter for the content.',
                                'selectAPreviousStatus' => 'Select at least one previous status.',
                                'selectANewStatus' => 'Select at least one new status.',
                                'selectPostType' => 'Select at least one post type.',
                                'selectCategory' => 'Select at least one category.',
                                'selectTaxonomy' => 'Select at least one taxonomy.',
                                'selectAReceiver' => 'Select at least one receiver.',
                                'selectAUser' => 'Select at least one user.',
                                'selectARole' => 'Select at least one role.',
                                'setASubject' => 'Type a subject for the notification.',
                                'setABody' => 'Type a body text for the notification.',
                            ],
                        ]
                    );
                }
            }

            if (in_array($hook_suffix, ['edit.php'])) {
                if (PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW === get_post_type()) {
            
                    wp_enqueue_script(
                        'improved-notifications-js',
                        plugin_dir_url(__FILE__) . 'assets/js/improved-notifications.js',
                        [
                            'jquery'
                        ],
                        PUBLISHPRESS_VERSION,
                        true
                    );

                    if ($this->module_enabled('notifications_log')) {
                        $logHandler = new NotificationsLogHandler();
                        $log_url    = admin_url('admin.php?page=pp-notif-log');
                        $log_total  = number_format_i18n($logHandler->getNotificationLogEntries(null, null, null, true));
                    } else {
                        $log_url    = '';
                        $log_total  = '';
                        
                    }
            
                    wp_localize_script(
                        'improved-notifications-js',
                        'ppNotif',
                        [
                            'log_total' => $log_total,
                            'log_url'   => $log_url,
                            'log_text'  => esc_html__('Notifications Log', 'publishpress'),
                        ]
                    );
                }
            
            }
        }

        /**
         * Filters the permalink output in the form, to disable it for the
         * workflow form.
         */
        public function filter_get_sample_permalink_html_workflow($return, $post_id, $new_title, $new_slug, $post)
        {
            if (PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW === $post->post_type) {
                $return = '';
            }

            return $return;
        }

        public function filter_row_actions($actions, $post)
        {
            if (PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW === $post->post_type) {
                unset($actions['view']);
            }

            return $actions;
        }

        public function action_meta_boxes_workflow()
        {
            add_meta_box(
                'publishpress_notif_workflow_div',
                __('Workflow Settings', 'publishpress'),
                [$this, 'publishpress_notif_workflow_metabox'],
                null,
                'advanced',
                'high'
            );

            add_meta_box(
                'publishpress_notif_workflow_options_div',
                __('Options', 'publishpress'),
                [$this, 'publishpress_notif_workflow_options_metabox'],
                null,
                'side',
                'high'
            );

            add_meta_box(
                'publishpress_notif_workflow_help_div',
                __('Help', 'publishpress'),
                [$this, 'publishpress_notif_workflow_help_metabox'],
                null,
                'side',
                'low'
            );
        }

        public function publishpress_notif_workflow_metabox()
        {
            // Adds the nonce field
            wp_nonce_field('publishpress_notif_save_metabox', 'publishpress_notif_metabox_events_nonce');

            $view = $this->get_service('view');

            $main_context = [];

            // Renders the event section
            $context = [
                'id' => 'event',
                'header' => __('When to notify?', 'publishpress'),
                'html' => apply_filters('publishpress_notif_render_metabox_section_event', ''),
                'class' => 'pure-u-1-3 pure-u-sm-1 pure-u-md-1 pure-u-lg-1-3',
            ];

            $main_context['section_event'] = $view->render('workflow_metabox_section', $context);

            // Renders the event content filter section
            $context = [
                'id' => 'event_content',
                'header' => __('For which content?', 'publishpress'),
                'html' => apply_filters('publishpress_notif_render_metabox_section_event_content', ''),
                'class' => 'pure-u-1-3 pure-u-sm-1 pure-u-md-1-2 pure-u-lg-1-3',
            ];

            $main_context['section_event_content'] = $view->render('workflow_metabox_section', $context);

            // Renders the receiver section
            $context = [
                'id' => 'receiver',
                'header' => __('Who to notify?', 'publishpress'),
                'html' => apply_filters('publishpress_notif_render_metabox_section_receiver', ''),
                'class' => 'pure-u-1-3 pure-u-sm-1 pure-u-md-1-2 pure-u-lg-1-3',
            ];

            $main_context['section_receiver'] = $view->render('workflow_metabox_section', $context);

            // Renders the content section
            $context = [
                'id' => 'content',
                'header' => __('What to say?', 'publishpress'),
                'html' => apply_filters('publishpress_notif_render_metabox_section_content', ''),
                'class' => 'pure-u-1',
            ];

            $main_context['section_content'] = $view->render('workflow_metabox_section', $context);

            // Renders the channel section
            $context = [
                'id' => 'channel',
                'html' => apply_filters('publishpress_notif_render_metabox_section_channel', ''),
            ];

            $main_context['section_channel'] = $view->render('workflow_metabox_section', $context);

            echo $view->render('workflow_metabox', $main_context, $this->viewsPath);
        }

        public function publishpress_notif_workflow_options_metabox()
        {
            $post = get_post();

            $view = $this->get_service('view');

            $context = [
                'options' => apply_filters('publishpress_notif_workflow_options', [], $post, $view),
            ];

            echo $view->render('workflow_metabox_options', $context);
        }

        private function getShortcodePostFields()
        {
            return apply_filters(
                'publishpress_notifications_shortcode_post_fields',
                [
                    'id',
                    'title',
                    'permalink',
                    'date',
                    'time',
                    'old_status',
                    'new_status',
                    'content',
                    'excerpt',
                    'post_type',
                    'edit_link',
                    'author_display_name',
                    'author_email',
                    'author_login',
                ]
            );
        }

        private function getShortcodeActorFields()
        {
            return apply_filters(
                'publishpress_notifications_shortcode_actor_fields',
                [
                    'id',
                    'login',
                    'url',
                    'display_name',
                    'first_name',
                    'last_name',
                    'email',
                ]
            );
        }

        private function getShortcodeWorkflowFields()
        {
            return apply_filters(
                'publishpress_notifications_shortcode_workflow_fields',
                [
                    'id',
                    'title',
                ]
            );
        }

        private function getShortcodeEdCommentsFields()
        {
            return apply_filters(
                'publishpress_notifications_shortcode_edcomments_fields',
                [
                    'id',
                    'content',
                    'author',
                    'author_email',
                    'author_url',
                    'author_ip',
                    'date',
                    'number',
                ]
            );
        }

        private function getShortcodeReceiverFields()
        {
            return apply_filters(
                'publishpress_notifications_shortcode_receiver_fields',
                [
                    'name',
                    'email',
                    'first_name',
                    'last_name',
                    'login',
                    'nickname',
                ]
            );
        }

        /**
         * Add the metabox for the help text
         */
        public function publishpress_notif_workflow_help_metabox()
        {
            $context = [
                'labels' => [
                    'validation_help' => __(
                        'Select at least one option for each section.',
                        'publishpress'
                    ),
                    'pre_text' => esc_html__(
                        'You can add dynamic information to the Subject or Body text using the following shortcodes:',
                        'publishpress'
                    ),
                    'content' => esc_html__('Content', 'publishpress'),
                    'edcomment' => esc_html__('Editorial Comment', 'publishpress'),
                    'actor' => esc_html__('User making changes or comments', 'publishpress'),
                    'workflow' => esc_html__('Workflow', 'publishpress'),
                    'format' => esc_html__('Format', 'publishpress'),
                    'receiver' => esc_html__('Receiver', 'publishpress'),
                    'shortcode' => esc_html__('shortcode', 'publishpress'),
                    'field' => esc_html__('field', 'publishpress'),
                    'format_text' => esc_html__(
                        'On each shortcode, you can select one or more fields. If more than one, they will be displayed separated by ", ".',
                        'publishpress'
                    ),
                    'available_fields' => esc_html__('Available fields', 'publishpress'),
                    'meta_fields' => esc_html__('Meta fields', 'publishpress'),
                    'read_more' => esc_html__(
                        'Click here to read more about shortcode options...',
                        'publishpress'
                    ),
                ],
                'psppno_post_fields_list' => esc_html(implode(', ', $this->getShortcodePostFields())),
                'psppno_actor_fields_list' => esc_html(implode(', ', $this->getShortcodeActorFields())),
                'psppno_workflow_fields_list' => esc_html(implode(', ', $this->getShortcodeWorkflowFields())),
                'psppno_edcomment_fields_list' => esc_html(implode(', ', $this->getShortcodeEdCommentsFields())),
                'psppno_receiver_fields_list' => esc_html(implode(', ', $this->getShortcodeReceiverFields())),
            ];

            echo $this->get_service('view')->render('workflow_help', $context);
        }

        /**
         * If it detects a notification workflow is being saved, triggers an
         * action for the workflow steps to be able to save their specific
         * metadata from the metaboxes.
         *
         * @param int $id Unique ID for the post being saved
         * @param WP_Post $post Post object
         *
         * @return int|null
         */
        public function save_meta_boxes($id, $post)
        {
            // Check if the saved post is a notification workflow
            if (PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW === $post->post_type) {
                // Authentication checks. Make sure the data came from the metabox
                if (! (
                    isset($_POST['publishpress_notif_metabox_events_nonce'])
                    && wp_verify_nonce(
                        sanitize_text_field($_POST['publishpress_notif_metabox_events_nonce']),
                        'publishpress_notif_save_metabox'
                    )
                )) {
                    return $id;
                }

                // Avoids autosave
                if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
                    return $id;
                }

                // Do the action so each workflow step class can save its metabox data
                do_action('publishpress_notif_save_workflow_metadata', $id, $post);
            }
        }

        private function isWPVIPEnvironment()
        {
            return function_exists('vip_powered_wpcom');
        }

        /**
         * Returns a list of published workflows.
         *
         * @return array
         */
        protected function get_published_workflows()
        {
            if (empty($this->published_workflows)) {
                $postsPerPage = $this->isWPVIPEnvironment() ? 100 : -1;

                // Build the query
                $query_args = [
                    'posts_per_page' => $postsPerPage,
                    'post_type' => PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW,
                    'post_status' => 'publish',
                    'no_found_rows' => true,
                    'cache_results' => true,
                    'meta_query' => [],
                ];

                $query = new WP_Query($query_args);

                $this->published_workflows = $query->posts;
            }

            return $this->published_workflows;
        }

        /**
         * Returns a list of workflows.
         *
         * @param array $meta_query
         *
         * @return array
         */
        public function get_workflows($meta_query = [])
        {
            $hash = md5(maybe_serialize($meta_query));

            // todo: Use WP Cache instead?
            if (! isset($this->workflows[$hash])) {
                $postsPerPage = $this->isWPVIPEnvironment() ? 100 : -1;

                // Build the query
                $query_args = [
                    'posts_per_page' => $postsPerPage,
                    'post_type' => PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW,
                    'no_found_rows' => true,
                    'cache_results' => true,
                    'meta_query' => $meta_query,
                ];

                $query = new WP_Query($query_args);

                $this->workflows[$hash] = $query->posts;

            }

            return $this->workflows[$hash];
        }

        /**
         * Add extra fields to the user profile for selecting where to receive notifications per workflow.
         *
         * @param WP_User $user
         */
        public function user_profile_fields($user)
        {
            // Check if the user has permission to see this field. If not, do nothing.
            if (! current_user_can('pp_set_notification_channel')) {
                return;
            }

            $view = $this->get_service('view');

            // Adds the nonce field
            wp_nonce_field('psppno_user_profile', 'psppno_user_profile_nonce');

            /**
             * Filters the list of notification channels to display in the
             * user profile.
             *
             * [
             *    'name': string
             *    'label': string
             *    'options': [
             *        'name'
             *        'html'
             *    ]
             * ]
             *
             * @param array
             */
            $default_channels = [
                [
                    'name' => 'mute',
                    'label' => esc_html__('Muted', 'publishpress'),
                    'options' => [],
                    'icon' => PUBLISHPRESS_URL . 'modules/improved-notifications/assets/img/icon-mute.png',
                ],
            ];
            $channels = apply_filters('psppno_filter_channels_user_profile', $default_channels);

            $workflow_channels = $this->get_user_workflow_channels($user);
            $channels_options = $this->get_user_workflow_channel_options($user);

            $context = [
                'labels' => [
                    'title' => esc_html__('Editorial Notifications', 'publishpress'),
                    'description' => esc_html__(
                        'Choose the channels where each workflow will send notifications to:',
                        'publishpress'
                    ),
                    'mute' => esc_html__('Muted', 'publishpress'),
                    'workflows' => esc_html__('Workflows', 'publishpress'),
                    'channels' => esc_html__('Channels', 'publishpress'),
                ],
                'workflows' => $this->get_published_workflows(),
                'channels' => $channels,
                'workflow_channels' => $workflow_channels,
                'channels_options' => $channels_options,
            ];

            echo $this->get_service('view')->render('user_profile_notification_channels', $context);
        }

        /**
         * Returns the list of channels for the workflows we find in the user's
         * meta data
         *
         * @param WP_User $user
         *
         * @return array
         */
        private function get_user_workflow_channels($user)
        {
            $workflows = $this->get_published_workflows();
            $channels = [];

            foreach ($workflows as $workflow) {
                $channel = get_user_meta($user->ID, 'psppno_workflow_channel_' . $workflow->ID, true);

                // If no channel is set yet, use the default one
                if (empty($channel)) {
                    /**
                     * Filters the default notification channel.
                     *
                     * @param string $default_channel
                     *
                     * @return string
                     */
                    $channel = $this->get_workflow_default_channel($workflow->ID);
                }

                $channels[$workflow->ID] = $channel;
            }

            return $channels;
        }

        private function get_workflow_default_channel($workflowId)
        {
            $channels = $this->module->options->default_channels;

            if (isset($channels[$workflowId])) {
                return $channels[$workflowId];
            }

            return apply_filters('psppno_filter_default_notification_channel', 'email');
        }

        /**
         * Validate data entered by the user
         *
         * @param array $new_options New values that have been entered by the user
         *
         * @return array $new_options Form values after they've been sanitized
         * @since 0.7
         *
         */
        public function settings_validate($new_options)
        {
            if (isset($new_options['channel_options']) && ! empty($new_options['channel_options'])) {
                foreach ($new_options['channel_options'] as &$item) {
                    $item = sanitize_text_field($item);
                }
            }

            if (isset($new_options['duplicated_notification_threshold'])) {
                $new_options['duplicated_notification_threshold'] = (int)$new_options['duplicated_notification_threshold'];
            } else {
                $new_options['duplicated_notification_threshold'] = Notification::DEFAULT_DUPLICATED_NOTIFICATION_THRESHOLD_IN_MINUTES;
            }

            return $new_options;
        }

        /**
         * Returns the list of options for the channels in the workflows we find
         * in the user's meta data.
         *
         * @param WP_User $user
         *
         * @return array
         */
        private function get_user_workflow_channel_options($user)
        {
            $workflows = $this->get_published_workflows();
            $options = [];

            foreach ($workflows as $workflow) {
                /**
                 * Filters the options for the channel in the workflow
                 *
                 * @param array $options
                 * @param int $user_id
                 * @param int $workflow_id
                 *
                 * @return array
                 */
                $channels_options = apply_filters(
                    'psppno_filter_workflow_channel_options',
                    [],
                    $user->ID,
                    $workflow->ID
                );
                $options[$workflow->ID] = $channels_options;
            }

            return $options;
        }

        /**
         * Saves the data coming from the user profile
         *
         * @param int $user_id
         */
        public function save_user_profile_fields($user_id)
        {
            if (! current_user_can('edit_user', $user_id)) {
                return false;
            }

            // Check the nonce field
            if (! (
                isset($_POST['psppno_user_profile_nonce'])
                && wp_verify_nonce(
                    sanitize_text_field($_POST['psppno_user_profile_nonce']),
                    'psppno_user_profile'
                )
            )) {
                return;
            }

            // Workflow Channels
            if (isset($_POST['psppno_workflow_channel']) && ! empty($_POST['psppno_workflow_channel'])) {
                // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                foreach ($_POST['psppno_workflow_channel'] as $workflow_id => $channel) {
                    update_user_meta(
                        $user_id,
                        'psppno_workflow_channel_' . (int)$workflow_id,
                        sanitize_key($channel)
                    );
                }
                // phpcs:enable
            }

            do_action('psppno_save_user_profile', $user_id);
        }

        /**
         * Add any necessary CSS to the WordPress admin
         *
         * @uses wp_enqueue_style()
         */
        public function add_admin_styles()
        {
            wp_enqueue_style('psppno-admin-css', $this->module_url . 'assets/css/admin.css');
            wp_enqueue_style('psppno-multiple-select', $this->module_url . 'assets/css/multiple-select.css');
            wp_enqueue_style('psppno-grid', $this->module_url . 'assets/css/grids-min.css');
            wp_enqueue_style(
                'psppno-grid-responsive',
                $this->module_url . 'assets/css/grids-responsive-min.css'
            );
            wp_enqueue_style('psppno-user-profile', $this->module_url . 'assets/css/user_profile.css');
        }

        /**
         * Display the PublishPress footer on the custom post pages
         */
        public function update_footer_admin($footer)
        {
            global $current_screen;

            if (PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW !== $current_screen->post_type) {
                return $footer;
            }

            $publishpress = $this->get_service('publishpress');

            $html = '<div class="pressshack-admin-wrapper">';
            $html .= $publishpress->settings->print_default_footer(
                $publishpress->modules->improved_notifications,
                false
            );
            // We do not close the div by purpose. The footer contains it.

            // Add the wordpress footer
            $html .= $footer;

            return $html;
        }

        /**
         * Filters the email message headers, to enable HTML emails
         *
         * @param array $message_headers
         * @param string $action
         * @param WP_Post $post
         *
         * @return array
         */
        public function filter_send_email_message_headers($message_headers, $action, $post)
        {
            if (is_string($message_headers) && ! empty($message_headers)) {
                $message_headers = [$message_headers];
            }

            if (! is_array($message_headers)) {
                $message_headers = [];
            }

            $message_headers[] = 'Content-Type: text/html; charset=UTF-8';

            // Set a default "from" name and email
            $publishpress = $this->get_service('publishpress');
            $email_from = $publishpress->notifications->get_email_from();
            $message_headers[] = sprintf('from: %s <%s>', $email_from['name'], $email_from['email']);

            return $message_headers;
        }

        /**
         * @param $channel
         * @param $workflowId
         *
         * @return mixed
         */
        public function filter_default_channel($channel, $workflowId = 0)
        {
            if (! empty($workflowId)) {
                $channel = $this->get_workflow_default_channel($workflowId);
            }

            return $channel;
        }
    }
}
