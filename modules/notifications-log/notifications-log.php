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

use PublishPress\Core\Ajax;
use PublishPress\Core\Error;
use PublishPress\Legacy\Auto_loader;
use PublishPress\Notifications\Traits\Dependency_Injector;
use PublishPress\Notifications\Workflow\Workflow;
use PublishPress\NotificationsLog\CliHandler;
use PublishPress\NotificationsLog\ModuleErrors;
use PublishPress\NotificationsLog\NotificationsLogHandler;
use PublishPress\NotificationsLog\NotificationsLogModel;
use PublishPress\NotificationsLog\NotificationsLogTable;

if (! class_exists('PP_Notifications_Log')) {
    /**
     * class PP_Notifications_Log
     */
    #[\AllowDynamicProperties]
    class PP_Notifications_Log extends PP_Module
    {
        use Dependency_Injector;

        const SETTINGS_SLUG = 'pp-notifications-log';

        public $module_name = 'notifications-log';

        public $module_url = '';

        /**
         * @var string
         */
        const MENU_SLUG = 'pp-notif-log';

        /**
         * The constructor
         */
        public function __construct()
        {
            global $publishpress;

            $this->viewsPath = dirname(dirname(dirname(__FILE__))) . '/views';

            $this->module_url = $this->get_module_url(__FILE__);

            // Register the module with PublishPress
            $args = [
                'title' => __('Notifications Log', 'publishpress'),
                'short_description' => false,
                'extended_description' => false,
                'module_url' => $this->module_url,
                'icon_class' => 'dashicons dashicons-feedback',
                'slug' => 'notifications-log',
                'default_options' => [
                    'enabled' => 'on',
                ],
                'notification_options' => true,
            ];

            // Apply a filter to the default options
            $args['default_options'] = apply_filters(
                'pp_notifications_queue_default_options',
                $args['default_options']
            );
            $args['default_options'] = apply_filters(
                'pp_notifications_queue_default_options',
                $args['default_options']
            );
            $this->module = $publishpress->register_module(
                PublishPress\Legacy\Util::sanitize_module_name($this->module_name),
                $args
            );

            Auto_loader::register('\\PublishPress\\NotificationsLog\\', __DIR__ . '/library');

            Error::getInstance()->registerModuleErrors(ModuleErrors::getInstance());

            parent::__construct();
        }

        /**
         * Initialize the module. Conditionally loads if the module is enabled
         */
        public function init()
        {
            if (is_admin()) {
                add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
                //add_action('publishpress_notif_post_metabox', [$this, 'postNotificationMetaBox']);
                add_action('publishpress_admin_submenu', [$this, 'action_admin_submenu'], 20);

                add_filter('set-screen-option', [$this, 'tableSetOptions'], 10, 3);
                add_action('wp_ajax_publishpress_search_post', [$this, 'ajaxSearchPost']);
                add_action('wp_ajax_publishpress_search_workflow', [$this, 'ajaxSearchWorkflow']);
                add_action('wp_ajax_publishpress_view_notification', [$this, 'ajaxViewNotification']);
                add_action('admin_init', [$this, 'processLogTableActions']);
            }

            add_action('publishpress_notif_notification_sending', [$this, 'actionNotificationSending'], 10, 7);
            add_action(
                'publishpress_notifications_skipped_duplicated',
                [$this, 'actionNotificationSkippedDueToDuplication'],
                10,
                6
            );

            add_filter('publishpress_notifications_scheduled_data', [$this, 'registerAsyncNotificationLogAndAddLogId']);
            add_action('publishpress_notifications_scheduled_cron_task', [$this, 'registerCronIdToLog'], 10, 2);
            add_action('publishpress_notifications_async_notification_sent', [$this, 'removeAsyncNotificationLog']);

            if (class_exists('WP_Cli')) {
                new CliHandler();
            }
        }

        /**
         * Settings page for notifications
         *
         * @since 1.18.1-beta.1
         */
        public function print_configure_view()
        {
        }

        /**
         * Enqueue necessary admin scripts
         *
         * @since 0.7
         *
         * @uses  wp_enqueue_script()
         */
        public function enqueueAdminScripts()
        {
            if ($this->is_whitelisted_functional_view()) {
                wp_enqueue_script('jquery-ui-dialog');
                wp_enqueue_style('wp-jquery-ui-dialog');

                $this->enqueue_datepicker_resources();

                wp_enqueue_style(
                    'pressshack-admin-css',
                    PUBLISHPRESS_URL . 'common/css/pressshack-admin.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'screen'
                );

                wp_enqueue_style(
                    'pp-admin-css',
                    PUBLISHPRESS_URL . 'common/css/publishpress-admin.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'screen'
                );

                wp_enqueue_script(
                    'publishpress-select2',
                    PUBLISHPRESS_URL . 'common/libs/select2/js/select2-full.min.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_script(
                    'publishpress-notifications-log',
                    $this->module_url . 'assets/js/admin.js',
                    [
                        'jquery-ui-dialog',
                        'jquery-ui-datepicker',
                        'publishpress-select2',
                    ],
                    PUBLISHPRESS_VERSION,
                    true
                );

                wp_localize_script(
                    'publishpress-notifications-log',
                    'ppNotifLog',
                    [
                        'nonce' => wp_create_nonce('notifications-log-admin'),
                        'text' => [
                            'allPosts' => __('All Posts', 'publishpress'),
                            'allWorkflows' => __('All Workflows', 'publishpress'),
                            'allActions' => __('All Actions', 'publishpress'),
                            'allChannels' => __('All Channels', 'publishpress'),
                            'allStatuses' => __('All Statuses', 'publishpress'),
                            'dialogTitle' => __('Notification', 'publishpress'),
                            'loading' => __('Loading...', 'publishpress'),
                        ],
                        'page_url' => admin_url('admin.php?page=pp-notif-log'),
                    ]
                );

                wp_enqueue_style(
                    'publishpress-notifications-log',
                    $this->module_url . 'assets/css/admin.css',
                    [],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_style(
                    'publishpress-select2-css',
                    plugins_url('common/libs/select2/css/select2-full.min.css', PUBLISHPRESS_FILE_PATH),
                    false,
                    PUBLISHPRESS_VERSION,
                    'all'
                );

                wp_enqueue_script(
                    'publishpress-select2',
                    plugins_url('common/libs/select2/js/select2-full.min.js', PUBLISHPRESS_FILE_PATH),
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );
            }
        }

        /**
         * Whether or not the current page is a user-facing PublishPress View
         *
         * @param string $module_name (Optional) Module name to check against
         *
         * @since 0.7
         */
        protected function is_whitelisted_functional_view($module_name = null)
        {
            global $current_screen;

            return $current_screen->base === 'admin_page_pp-notif-log';
        }

        /**
         * @param WP_Post
         */
        public function postNotificationMetaBox($post)
        {
            $logHandler = new NotificationsLogHandler();
            $logCount = $logHandler->getNotificationLogEntries($post->ID, null, null, true); ?>
            <div class="publishpress_notifications_log">
                <h3><?php
                    echo esc_html__('Notifications Log', 'publishpress'); ?></h3>

                <?php
                if ($logCount > 0) : ?>
                    <a href="/wp-admin/admin.php?page=pp-notif-log&orderby=date&order=desc&post_id=<?php
                    echo (int)$post->ID; ?>"
                       class="view_log"><?php
                        echo esc_html(
                            sprintf(
                                _n(
                                    '%s notification found.',
                                    '%s notifications found.',
                                    $logCount,
                                    'publishpress'
                                ),
                                $logCount
                            )
                        ); ?></a>
                <?php
                else: ?>
                    <p class="no-workflows"><?php
                        echo esc_html__('No notifications found.', 'publishpress'); ?></p>
                <?php
                endif; ?>
            </div>
            <?php
        }

        public function registerAsyncNotificationLogAndAddLogId($data)
        {
            $logHandler = new NotificationsLogHandler();

            $logData = [
                'event' => $data['event_args']['event'],
                'user_id' => $data['event_args']['user_id'],
                'workflow_id' => $data['workflow_id'],
                'old_status' => isset($data['event_args']['params']['old_status']) ? $data['event_args']['params']['old_status'] : null,
                'new_status' => isset($data['event_args']['params']['new_status']) ? $data['event_args']['params']['new_status'] : null,
                'post_id' => isset($data['event_args']['params']['post_id']) ? $data['event_args']['params']['post_id'] : null,
                'comment_id' => isset($data['event_args']['params']['comment_id']) ? $data['event_args']['params']['comment_id'] : null,
                'async' => true,
                'status' => 'scheduled',
                'channel' => isset($data['channel']) ? $data['channel'] : null,
                'receiver' => isset($data['receiver']) ? $data['receiver'] : null,
                'success' => isset($data['success']) ? $data['success'] : null,
                'error' => isset($data['error']) ? $data['error'] : null,
                'event_args' => $data['event_args'],
            ];

            $data['log_id'] = $logHandler->registerLog($logData);

            return $data;
        }

        public function registerCronIdToLog($data, $cronId)
        {
            update_comment_meta($data['log_id'], NotificationsLogModel::META_NOTIF_CRON_ID, $cronId);
        }

        public function removeAsyncNotificationLog($params)
        {
            if (isset($params['log_id'])) {
                $comment = get_comment($params['log_id']);

                if ($comment instanceof WP_Comment) {
                    $log = new NotificationsLogModel($comment);

                    $log->archive();
                }
            }
        }

        /**
         * @param Workflow $workflow
         * @param $channel
         * @param $receiver
         * @param $subject
         * @param $body
         * @param $deliveryResult
         * @param $async
         */
        public function actionNotificationSending(
            $workflow,
            $channel,
            $receiver,
            $subject,
            $body,
            $deliveryResult,
            $async
        )
        {
            $logHandler = new NotificationsLogHandler();

            $error = '';

            if (true !== $deliveryResult) {
                $error = apply_filters(
                    'publishpress_notif_error_log',
                    $error,
                    $deliveryResult,
                    $receiver,
                    $subject,
                    $body
                );
            }

            $eventArgs = $workflow->event_args;

            $logData = [
                'event' => $eventArgs['event'],
                'user_id' => $eventArgs['user_id'],
                'workflow_id' => $workflow->workflow_post->ID,
                'content' => maybe_serialize(['subject' => $subject, 'body' => $body]),
                'status' => 'sent',
                'channel' => $channel,
                'receiver' => $receiver['receiver'],
                'receiver_group' => $receiver['group'],
                'success' => $deliveryResult,
                'error' => $error,
                'async' => $async,
                'event_args' => $eventArgs,
            ];

            if (isset($receiver['subgroup'])) {
                $logData['receiver_subgroup'] = $receiver['subgroup'];
            }

            if (isset($eventArgs['params']['old_status'])) {
                $logData['old_status'] = $eventArgs['params']['old_status'];
            }

            if (isset($eventArgs['params']['new_status'])) {
                $logData['new_status'] = $eventArgs['params']['new_status'];
            }

            if (isset($eventArgs['params']['comment_id'])) {
                $logData['comment_id'] = $eventArgs['params']['comment_id'];
            }

            if (isset($eventArgs['params']['post_id'])) {
                $logData['post_id'] = $eventArgs['params']['post_id'];
            }

            $logHandler->registerLog($logData);
        }

        /**
         * @param Workflow $workflow
         * @param $receiver
         * @param $content
         * @param $channel
         * @param $async
         * @param $threshold
         * @throws Exception
         */
        public function actionNotificationSkippedDueToDuplication(
            $workflow,
            $receiver,
            $content,
            $channel,
            $async,
            $threshold
        )
        {
            $logHandler = new NotificationsLogHandler();

            $eventArgs = $workflow->event_args;

            $logData = [
                'event' => $eventArgs['event'],
                'user_id' => $eventArgs['user_id'],
                'workflow_id' => $workflow->workflow_post->ID,
                'content' => maybe_serialize($content),
                'status' => 'skipped',
                'channel' => $channel,
                'receiver' => $receiver['receiver'],
                'receiver_group' => $receiver['group'],
                'success' => false,
                'error' => sprintf(
                    __(
                        'This notification is very similar to another one sent less than %d minutes ago for the same receiver',
                        'publishpress'
                    ),
                    $threshold
                ),
                'async' => $async,
                'event_args' => $eventArgs,
            ];

            if (isset($receiver['subgroup'])) {
                $logData['receiver_subgroup'] = $receiver['subgroup'];
            }

            if (isset($eventArgs['params']['old_status'])) {
                $logData['old_status'] = $eventArgs['params']['old_status'];
            }

            if (isset($eventArgs['params']['new_status'])) {
                $logData['new_status'] = $eventArgs['params']['new_status'];
            }

            if (isset($eventArgs['params']['comment_id'])) {
                $logData['comment_id'] = $eventArgs['params']['comment_id'];
            }

            if (isset($eventArgs['params']['post_id'])) {
                $logData['post_id'] = $eventArgs['params']['post_id'];
            }

            $logHandler->registerLog($logData);
        }

        /**
         * Add necessary things to the admin menu
         */
        public function action_admin_submenu()
        {
            if (false === $this->getReadWorkflowsCapability()) {
                return;
            }

            $publishpress = $this->get_service('publishpress');

            // Main Menu
            $hook = add_submenu_page(
                '',
                esc_html__('Notifications Log', 'publishpress'),
                esc_html__('Notifications Log', 'publishpress'),
                $this->getReadWorkflowsCapability(),
                self::MENU_SLUG,
                [$this, 'render_admin_page'],
                40
            );

            add_action('load-' . $hook, [$this, 'addScreenOptions']);
        }

        protected function getReadWorkflowsCapability()
        {
            return apply_filters('pp_view_notifications_cap', 'read_pp_notif_workflow');
        }

        protected function currentUserCanReadWorkflows()
        {
            return current_user_can($this->getReadWorkflowsCapability());
        }

        public function addScreenOptions()
        {
            $option = 'per_page';
            $args = [
                'label' => 'Logs',
                'default' => NotificationsLogTable::POSTS_PER_PAGE,
                'option' => 'logs_per_page',
            ];
            add_screen_option($option, $args);
        }

        public function tableSetOptions(
            $status,
            $option,
            $value
        )
        {
            return $value;
        }

        /**
         * Create the content overview view. This calls lots of other methods to do its work. This will
         * output any messages, create the table navigation, then print the columns based on
         * get_num_columns(), which will in turn print the stories themselves.
         */
        public function render_admin_page()
        {
            $publishpress = $this->get_service('publishpress');
            $publishpress->settings->print_default_header($publishpress->modules->notifications_log);

            $logTable = new NotificationsLogTable(new NotificationsLogHandler());
            $logTable->views();

            //Fetch, prepare, sort, and filter our data...
            $logTable->prepare_items();

            $page = '';
            if (isset($_REQUEST['page'])) {
                $page = sanitize_text_field($_REQUEST['page']);
            } ?>
            <div class="wrap">
                <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
                <form id="log-filter" method="get">
                    <!-- For plugins, we also need to ensure that the form posts back to our current page -->
                    <input type="hidden" name="page" value="<?php
                    echo esc_attr($page) ?>"/>

                    <!-- Now we can render the completed list table -->
                    <?php
                    $logTable->display() ?>
                </form>

            </div>
            <?php

            $publishpress->settings->print_default_footer($publishpress->modules->notifications_log);
        }

        public function ajaxSearchPost()
        {
            $ajax = Ajax::getInstance();

            if (! isset($_GET['nonce']) || ! wp_verify_nonce(
                    sanitize_text_field($_GET['nonce']),
                    'notifications-log-admin'
                )) {
                $ajax->sendJsonError(Error::ERROR_CODE_INVALID_NONCE);
            }

            if (false === $this->currentUserCanReadWorkflows()) {
                $ajax->sendJsonError(Error::ERROR_CODE_ACCESS_DENIED);
            }

            global $wpdb;

            $commentType = NotificationsLogModel::COMMENT_TYPE;
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

        public function ajaxSearchWorkflow()
        {
            $ajax = Ajax::getInstance();

            if (! isset($_GET['nonce']) || ! wp_verify_nonce(
                    sanitize_text_field($_GET['nonce']),
                    'notifications-log-admin'
                )) {
                $ajax->sendJsonError(Error::ERROR_CODE_INVALID_NONCE);
            }

            if (false === $this->currentUserCanReadWorkflows()) {
                $ajax->sendJsonError(Error::ERROR_CODE_ACCESS_DENIED);
            }

            global $wpdb;

            $metaKeyWorkflow = NotificationsLogModel::META_NOTIF_WORKFLOW_ID;
            $search = isset($_GET['search']) ? $wpdb->esc_like(sanitize_text_field($_GET['search'])) : '';
            $search = '%' . $search . '%';

            $posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT cm.meta_value AS 'ID', p.post_title AS 'post_title'
                FROM {$wpdb->commentmeta} AS cm
                LEFT JOIN {$wpdb->posts} AS p ON (cm.meta_value = p.ID)
                WHERE cm.meta_key = %s
                AND (p.post_title LIKE %s OR p.ID LIKE %s)",
                    $metaKeyWorkflow,
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

        public function processLogTableActions()
        {
            $currentAction = null;

            if (wp_doing_ajax() || wp_doing_cron()) {
                return;
            }

            if (isset($_REQUEST['action']) && -1 != $_REQUEST['action']) {
                $currentAction = sanitize_text_field($_REQUEST['action']);
            }

            if (empty($currentAction)) {
                return;
            }

            if (! isset($_GET['page']) || $_GET['page'] !== 'pp-notif-log') {
                return;
            }

            check_admin_referer('bulk-notifications_log');

            $shouldRedirect = false;

            if (NotificationsLogTable::BULK_ACTION_DELETE === $currentAction) {
                $ids = isset($_GET['notification_log']) ? array_map('intval', (array)$_GET['notification_log']) : [];

                if (! empty($ids)) {
                    foreach ($ids as $id) {
                        $logComment = get_comment($id);

                        if (! empty($logComment)) {
                            $log = new NotificationsLogModel($logComment);
                            $log->delete();
                        }
                    }
                }

                $shouldRedirect = true;
            } elseif (NotificationsLogTable::BULK_ACTION_DELETE_ALL === $currentAction) {
                $logHandler = new NotificationsLogHandler();
                $notifications = $logHandler->getNotificationLogEntries(
                    null,
                    'comment_date',
                    'desc',
                    false,
                    [],
                    null,
                    null
                );

                if (! empty($notifications)) {
                    foreach ($notifications as $logComment) {
                        if (! empty($logComment)) {
                            $log = new NotificationsLogModel($logComment);
                            $log->delete();
                        }
                    }
                }

                $shouldRedirect = true;
            } elseif (NotificationsLogTable::BULK_ACTION_TRY_AGAIN === $currentAction) {
                $ids = isset($_GET['notification_log']) ? array_map('intval', (array)$_GET['notification_log']) : [];

                if (! empty($ids)) {
                    foreach ($ids as $id) {
                        $logComment = get_comment($id);

                        if (! empty($logComment)) {
                            $log = new NotificationsLogModel($logComment);

                            $scheduler = $this->get_service('notification_scheduler');
                            $scheduler->scheduleNotification($log->workflowId, $log->eventArgs);

                            $log->delete();
                        }
                    }
                }

                $shouldRedirect = true;
            }

            if ($shouldRedirect) {
                wp_redirect(admin_url('admin.php?page=pp-notif-log'));
                exit();
            }

            return;
        }

        public function ajaxViewNotification()
        {
            $errorHandler = Error::getInstance();

            if (! isset($_REQUEST['nonce']) || ! wp_verify_nonce(
                    sanitize_text_field($_REQUEST['nonce']),
                    'notifications-log-admin'
                )) {
                $errorHandler->wpDie(Error::ERROR_CODE_INVALID_NONCE);
            }

            if (false === $this->currentUserCanReadWorkflows()) {
                $errorHandler->wpDie(Error::ERROR_CODE_ACCESS_DENIED);
            }

            $output = '';

            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            $receiver = isset($_REQUEST['receiver']) ? sanitize_text_field($_REQUEST['receiver']) : '';
            $channel = isset($_REQUEST['channel']) ? sanitize_text_field($_REQUEST['channel']) : '';

            if (empty($receiver)) {
                $errorHandler->wpDie(ModuleErrors::ERROR_CODE_INVALID_RECEIVER);
            }

            if (empty($channel)) {
                $errorHandler->wpDie(ModuleErrors::ERROR_CODE_INVALID_CHANNEL);
            }

            if (! empty($id)) {
                $comment = get_comment($id);
                if (is_object($comment)) {
                    $log = new NotificationsLogModel($comment);

                    $log->switchToTheBlog();

                    if ($log->status === 'scheduled') {
                        $workflow = Workflow::load_by_id($log->workflowId);

                        $workflow->event_args = $log->eventArgs;

                        $content_template = $workflow->get_content();
                        $content = $workflow->do_shortcodes_in_content($content_template, $receiver, $channel);
                    } else {
                        $content = $log->content;
                    }

                    ob_start(); ?>
                    <div class="preview-notification">
                        <div class="subject"><label><?php
                                _e(
                                    'Subject:',
                                    'publishpress'
                                ); ?></label><?php
                            echo esc_html($content['subject']); ?></div>
                        <div class="content">
                            <label>
                                <?php
                                esc_html_e('Content:', 'publishpress'); ?>
                            </label>
                            <?php
                            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo wpautop($content['body']);
                            // phpcs:enable ?>
                        </div>
                        <?php
                        if ($log->status === 'scheduled') : ?>
                            <div class="notice notice-warning"><?php
                                echo esc_html__(
                                    'This is a preview of the scheduled message. The content can still change until the notification is sent.',
                                    'publishpress'
                                ); ?></div>
                        <?php
                        endif; ?>
                    </div>
                    <?php
                    $output = ob_get_clean();

                    $log->restoreCurrentBlog();
                } else {
                    $errorHandler->wpDie(ModuleErrors::ERROR_CODE_NOTIFICATION_LOG_NOT_FOUND);
                }
            } else {
                $errorHandler->wpDie(ModuleErrors::ERROR_CODE_NOTIFICATION_LOG_NOT_FOUND);
            }

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $output;
            die();
        }
    }
}
