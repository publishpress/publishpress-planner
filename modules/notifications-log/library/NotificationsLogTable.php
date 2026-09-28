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

namespace PublishPress\NotificationsLog;

use PublishPress\Notifications\Traits\Dependency_Injector;
use WP_List_Table;

global $title;

if (!isset($title)) {
    $title = '';
}

if (! class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class NotificationsLogTable
 *
 * @package PublishPress\NotificationsLog
 */
class NotificationsLogTable extends WP_List_Table
{
    use Dependency_Injector;

    const POSTS_PER_PAGE = 20;

    const BULK_ACTION_DELETE = 'delete';

    const BULK_ACTION_DELETE_ALL = 'delete_all';

    const BULK_ACTION_TRY_AGAIN = 'try_again';

    /**
     * @var NotificationsLogHandler
     */
    private $logHandler;

    /**
     * NotificationsLogTable constructor.
     *
     * @param $logHandler
     */
    public function __construct($logHandler)
    {
        global $status, $page;

        //Set parent defaults
        parent::__construct(
            [
                'singular' => 'notification_log',     //singular name of the listed records
                'plural' => 'notifications_log',    //plural name of the listed records
                'ajax' => false        //does this table support ajax?
            ]
        );

        $this->logHandler = $logHandler;
        $this->logHandler = $logHandler;
    }

    private function wrapInALink($text, $url, $blankTarget = true)
    {
        return sprintf(
            '<a target="%s" href="%s">%s</a>',
            $blankTarget ? '_blank' : '',
            $url,
            $text
        );
    }

    /**
     * @param object $item
     * @param string $column_name
     *
     * @return mixed|string|void
     */
    public function column_default($item, $column_name)
    {
        $log = new NotificationsLogModel($item);
        $output = '';

        $log->switchToTheBlog();

        switch ($column_name) {
            case 'event':
                if ($log->workflowId !== null) {
                    $user = get_user_by('id', $log->userId);
                    $userNicename = (! is_wp_error($user) && is_object($user)) ? $user->user_nicename : '';
                    $actionParams = apply_filters('publishpress_notifications_action_params_for_log', '', $log);

                    $output .= apply_filters('publishpress_notifications_event_label', $log->event, $log->event);
                    $output .= '<div class="muted">';
                    $output .= '<div>' . sprintf(
                            __('Workflow: %s', 'publishpress'),
                            $this->wrapInALink(
                                $log->workflowTitle,
                                admin_url('post.php?post=' . esc_attr($log->workflowId) . '&action=edit')
                            )
                        ) . '</div>';

                    if (! empty($actionParams)) {
                        $output .= '<div>' . $actionParams . '</div>';
                    }

                    $output .= '<div>' . sprintf(
                            __('User: %s (%d)', 'publishpress'),
                            $this->wrapInALink(
                                $userNicename,
                                admin_url('user-edit.php?user_id=' . $log->userId)
                            ),
                            $log->userId
                        ) . '</div>';


                    $output .= '</div>';
                } else {
                    $output .= '-';
                }

                break;

            case 'content':
                if ($log->workflowId !== null) {
                    $post = get_post($log->postId);
                    $postType = get_post_type_object($post->post_type);
                    $postTypeLabels = get_post_type_labels($postType);

                    $output .= $this->wrapInALink(
                        $log->postTitle,
                        admin_url('post.php?post=' . esc_attr($log->postId) . '&action=edit')
                    );

                    $output .= '<div class="muted">';
                    $output .= '<div>' . sprintf(
                            __('Post type: %s', 'publishpress'),
                            $postTypeLabels->singular_name
                        ) . '</div>';
                    $output .= '<div>' . sprintf(__('Post ID: %d', 'publishpress'), $log->postId) . '</div>';

                    if ($log->isFromAnotherBlog()) {
                        $output .= '<div>' . sprintf(__('Blog ID: %d', 'publishpress'), $log->blogId) . '</div>';
                    }

                    $output .= '</div>';
                } else {
                    $output .= '-';
                }
                break;

            case 'receiver':
                if ($log->workflowId !== null) {
                    $receivers = $log->getReceiversByGroup();
                    $receiversCount = 0;

                    $output .= '<ul class="publishpress-notifications-receivers">';
                    foreach ($receivers as $group => $groupReceivers) {
                        $output .= sprintf(
                            '<li class="receiver-title">%s:</li>',
                            apply_filters('publishpress_notifications_receiver_group_label', $group)
                        );

                        $lastSubgroup = null;

                        foreach ($groupReceivers as $receiverData) {
                            $receiver = $receiverData['receiver'];

                            // Do not repeat the same subgroup
                            if (
                                isset($receiverData['subgroup'])
                                && $receiverData['subgroup'] !== $lastSubgroup
                                && ! empty($receiverData['subgroup'])
                            ) {
                                $output .= sprintf(
                                    '<li class="receiver-subgroup"><span>%s</span></li>',
                                    $receiverData['subgroup']
                                );

                                $lastSubgroup = $receiverData['subgroup'];
                            }

                            $receiverText = apply_filters(
                                'publishpress_notifications_log_receiver_text',
                                $receiver,
                                $receiverData,
                                $log->workflowId
                            );

                            $output .= sprintf(
                                '<li><i title="%s" class="dashicons dashicons-visibility view-log" data-id="%d" data-receiver-text="%s" data-receiver="%s" data-channel="%s"></i><i class="channel-icon %s"></i>',
                                $log->status === 'scheduled' ? __('Preview the message') : __('View the message'),
                                $log->id,
                                esc_attr(wp_strip_all_tags($receiverText)),
                                esc_attr($receiverData['receiver']),
                                esc_attr($receiverData['channel']),
                                apply_filters('publishpress_notifications_channel_icon_class', $receiverData['channel'])
                            );

                            $output .= $receiverText;
                            $output .= '</li>';

                            $receiversCount++;
                        }
                    }
                    $output .= '</ul>';

                    // Add the slide effect for scheduled notifications
                    if ($receiversCount > 4 && $log->status === 'scheduled') {
                        $output = sprintf(
                            '<a href="#" class="slide-closed-text">%s <i class="dashicons dashicons-arrow-down-alt2"></i></a><div class="slide">%s</div>',
                            sprintf(
                                __('Scheduled for %d receivers. Click here to display them.', 'publishpress'),
                                $receiversCount
                            ),
                            $output
                        );
                    }
                } else {
                    $output .= '-';
                }

                break;

            case
            'status':
                $output .= sprintf(
                    '<div class="publishpress-notifications-status status-%s">',
                    esc_attr($log->status)
                );

                if ($log->status === 'scheduled') {
                    $cronTask = $log->getCronTask();

                    if (false !== $cronTask) {
                        $offset = (int)get_option('gmt_offset', 0) * 60 * 60;
                        $scheduledTime = $cronTask['time'] + $offset;
                        $currentTime = current_time('timestamp');

                        if ($scheduledTime < $currentTime) {
                            $label = __(' Scheduled, but late', 'publishpress');
                        } else {
                            $label = __(' Scheduled', 'publishpress');
                        }

                        $output .= sprintf(
                            '<i class="dashicons dashicons-clock"></i> %s - %s',
                            $label,
                            date_i18n(
                                'Y-m-d H:i:s',
                                $scheduledTime
                            )
                        );
                    } else {
                        $output .= sprintf(
                            '<span class="error"><i class="dashicons dashicons-no"></i> %s</span>',
                            __('Failed', 'publishpress')
                        );

                        $output .= sprintf(
                            '<div class="muted">%s: %s</div>',
                            __('Error', 'publishpress'),
                            __('The notification was set as "Scheduled" but the cron task is not found', 'publishpress')
                        );
                    }
                } else {
                    if ($log->success) {
                        $output .= '<i class="dashicons dashicons-yes-alt"></i> ' . __('Sent', 'publishpress');
                    } else {
                        if ('skipped' == $log->status) {
                            $output .= '<i class="dashicons dashicons-warning"></i> ' . __('Skipped', 'publishpress');
                        } else {
                            $output .= '<i class="dashicons dashicons-no"></i> ' . __('Failed', 'publishpress');
                        }

                        if (! empty($log->error)) {
                            $output .= '<span class="error"> - ' . $log->error . '</span>';
                        }
                    }
                }

                $output .= sprintf(
                    '<div class="muted">(%s)</div>',
                    $log->async ? __('asynchronous', 'publishpress') : __('synchronous', 'publishpress')
                );

                $output .= '</div>';

                break;

            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }

        if ('scheduled' === $log->status) {
            $output = '<div class="scheduled-wrapper">' . $output . '</div>';
        }

        $log->restoreCurrentBlog();

        return $output;
    }

    /**
     * @param object $item
     *
     * @return string|void
     */
    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],
            $item->comment_ID
        );
    }

    /**
     * @param $item
     *
     * @return string
     */
    public function column_date($item)
    {
        $actions = [];
        $log = new NotificationsLogModel($item);
        $nonce = wp_create_nonce('bulk-notifications_log');

        if ('scheduled' === $log->status || 'error' === $log->status) {
            $actions['try_again'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(
                    add_query_arg(
                        [
                            'action' => 'try_again',
                            $this->_args['singular'] => $log->id,
                            '_wpnonce' => $nonce,
                        ]
                    )
                ),
                __('Reschedule', 'publishpress')
            );
        }

        $actions['delete'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(
                add_query_arg(
                    [
                        'action' => 'delete',
                        $this->_args['singular'] => $log->id,
                        '_wpnonce' => $nonce,
                    ]
                )
            ),
            __('Delete', 'publishpress')
        );

        $additionalText = '';
        if ($log->isFromAnotherBlog()) {
            $log->switchToTheBlog();
            $blog = get_blog_details($log->blogId);
            $additionalText .= '<div class="muted">' . __(
                    'Blog: ',
                    'publishpress'
                ) . $log->blogId . ' - ' . $blog->blogname . '</div>';
            $log->restoreCurrentBlog();
        }

        //Return the title contents
        return sprintf(
            '%1$s <div class="muted">ID: %2$s</div>%4$s%3$s',
            $item->comment_date,
            $item->comment_ID,
            $this->row_actions($actions),
            $additionalText
        );
    }

    /**
     * @return array
     */
    public function get_bulk_actions()
    {
        return [
            self::BULK_ACTION_TRY_AGAIN => __('Reschedule', 'publishpress'),
            self::BULK_ACTION_DELETE => __('Delete', 'publishpress'),
            self::BULK_ACTION_DELETE_ALL => __('Delete All', 'publishpress'),
        ];
    }

    public function prepare_items()
    {
        $this->process_bulk_action();

        $currentUserId = get_current_user_id();
        $per_page = get_user_meta($currentUserId, 'logs_per_page', true);
        if (empty($per_page)) {
            $per_page = self::POSTS_PER_PAGE;
        }

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $current_page = $this->get_pagenum();

        $filters = [];
        if (isset($_REQUEST['status'])) {
            $filters['status'] = sanitize_text_field($_REQUEST['status']);
        }

        if (isset($_REQUEST['s'])) {
            $filters['search'] = sanitize_text_field($_REQUEST['s']);
        }

        if (isset($_REQUEST['workflow_id'])) {
            $filters['workflow_id'] = (int)$_REQUEST['workflow_id'];
        }

        $postId = null;
        if (isset($_REQUEST['post_id'])) {
            $postId = (int)$_REQUEST['post_id'];
        }

        if (isset($_REQUEST['event'])) {
            $filters['event'] = sanitize_text_field($_REQUEST['event']);
        }

        if (isset($_REQUEST['channel'])) {
            $filters['channel'] = sanitize_text_field($_REQUEST['channel']);
        }

        if (isset($_REQUEST['date_begin']) && isset($_REQUEST['date_end'])) {
            $filters['date_begin'] = sanitize_text_field($_REQUEST['date_begin']);
            $filters['date_end'] = sanitize_text_field($_REQUEST['date_end']);
        }

        if (isset($_REQUEST['receiver'])) {
            $filters['receiver'] = sanitize_text_field($_REQUEST['receiver']);
        }

        $total_items = $this->logHandler->getNotificationLogEntries($postId, null, null, true, $filters);

        $orderBy = (! empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'comment_date'; //If no sort, default to title
        $order = (! empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc'; //If no order, default to asc

        $this->items = $this->logHandler->getNotificationLogEntries(
            $postId,
            $orderBy,
            $order,
            false,
            $filters,
            $per_page,
            $current_page
        );

        $this->set_pagination_args(
            [
                'total_items' => $total_items,
                'per_page' => $per_page,
                'total_pages' => ceil($total_items / $per_page),
            ]
        );
    }

    /**
     * @return array
     */
    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'date' => __('Date', 'publishpress'),
            'event' => __('When to notify?', 'publishpress'),
            'content' => __('For which content?', 'publishpress'),
            'receiver' => __('Who to notify?', 'publishpress'),
            'status' => __('Status', 'publishpress'),
        ];
    }

    /**
     * @return array
     */
    public function get_sortable_columns()
    {
        return [
            'date' => ['date', true],
        ];
    }

    protected function get_views()
    {
        return [
            'all' => '<a href="' . esc_url(add_query_arg('status', 'all')) . '">' . __(
                    'All',
                    'publishpress'
                ) . '</a>',
            'success' => '<a href="' . esc_url(add_query_arg('status', 'success')) . '">' . __(
                    'Success',
                    'publishpress'
                ) . '</a>',
            'skipped' => '<a href="' . esc_url(add_query_arg('status', 'skipped')) . '">' . __(
                    'Skipped',
                    'publishpress'
                ) . '</a>',
            'scheduled' => '<a href="' . esc_url(add_query_arg('status', 'scheduled')) . '">' . __(
                    'Scheduled',
                    'publishpress'
                ) . '</a>',
            'error' => '<a href="' . esc_url(add_query_arg('status', 'error')) . '">' . __(
                    'Error',
                    'publishpress'
                ) . '</a>',
        ];
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination
     *
     * @param string $which
     *
     * @since 3.1.0
     *
     */
    protected function extra_tablenav($which)
    {
        if ('top' !== $which) {
            return;
        }

        // Post
        $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
        $selectedOptionEscaped = '';
        if (! empty($postId)) {
            $post = get_post($postId);

            $selectedOptionEscaped = '<option selected="selected" value="' . esc_attr(
                    $postId
                ) . '">' . esc_html($post->post_title) . '</option>';
        }

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<label class="screen-reader-text" for="publishpress-notifications-filter-posts">' . esc_html__('Filter by post', 'publishpress') . '</label>';
        echo '<select class="filter-posts" id="publishpress-notifications-filter-posts" name="post_id">' . $selectedOptionEscaped . '</select>';
        // phpcs:enable

        // Workflow
        $workflowId = isset($_GET['workflow_id']) ? (int)$_GET['workflow_id'] : 0;
        $selectedOptionEscaped = '';
        if (! empty($workflowId)) {
            $workflow = get_post($workflowId);

            $selectedOptionEscaped = '<option selected="selected" value="' . esc_attr(
                    $workflowId
                ) . '">' . esc_html($workflow->post_title) . '</option>';
        }

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<label class="screen-reader-text" for="publishpress-notifications-filter-workflows">' . esc_html__('Filter by workflow', 'publishpress') . '</label>';
        echo '<select class="filter-workflows" id="publishpress-notifications-filter-workflows" name="workflow_id">' . $selectedOptionEscaped . '</select>';
        // phpcs:enable

        // Event
        $selectedAction = isset($_GET['event']) ? sanitize_text_field($_GET['event']) : '';

        echo '<label class="screen-reader-text" for="publishpress-notifications-filter-events">' . esc_html__('Filter by event', 'publishpress') . '</label>';
        echo '<select class="filter-actions" id="publishpress-notifications-filter-events" name="event">';
        $events = apply_filters('publishpress_notifications_workflow_events', []);

        echo '<option value="">' . esc_html__('All events', 'publishpress') . '</option>';

        foreach ($events as $event) {
            $actionLabel = apply_filters('publishpress_notifications_event_label', $event, $event);
            echo '<option ' . selected(
                    $event,
                    $selectedAction
                ) . ' value="' . esc_attr($event) . '">' . esc_html($actionLabel) . '</option>';
        }
        echo '</select>';

        // Channel
        $selectedChannel = isset($_GET['channel']) ? sanitize_text_field($_GET['channel']) : '';

        echo '<label class="screen-reader-text" for="publishpress-notifications-filter-channels">' . esc_html__('Filter by channel', 'publishpress') . '</label>';
        echo '<select class="filter-channels" id="publishpress-notifications-filter-channels" name="channel">';
        $channels = apply_filters('psppno_filter_channels', []);

        echo '<option value="">' . esc_html__('All channels', 'publishpress') . '</option>';

        foreach ($channels as $channel) {
            echo '<option ' . selected(
                    $channel->name,
                    $selectedChannel
                ) . ' value="' . esc_attr($channel->name) . '">' . esc_html($channel->label) . '</option>';
        }
        echo '</select>';

        echo '<br clear="all">';

        $dateBegin = isset($_GET['date_begin']) ? sanitize_text_field($_GET['date_begin']) : '';
        $dateEnd = isset($_GET['date_end']) ? sanitize_text_field($_GET['date_end']) : '';

        echo '<div class="filter-2nd-line">';
        echo '<span class="filter-dates">';
        echo '<label class="screen-reader-text" for="publishpress-notifications-filter-date-begin">' . esc_html__('From date', 'publishpress') . '</label>';
        echo '<input type="text" class="filter-date-begin" id="publishpress-notifications-filter-date-begin" name="date_begin" value="' . esc_attr(
                $dateBegin
            ) . '" placeholder="' . esc_html__(
                'From date',
                'publishpress'
            ) . '" />&nbsp;';
        echo '&nbsp;<label class="screen-reader-text" for="publishpress-notifications-filter-date-end">' . esc_html__('To date', 'publishpress') . '</label>';
        echo '&nbsp;<input type="text" class="filter-date-end" id="publishpress-notifications-filter-date-end" name="date_end" value="' . esc_attr(
                $dateEnd
            ) . '" placeholder="' . esc_html__(
                'To date',
                'publishpress'
            ) . '" />&nbsp;';
        echo '</span>';

        // Receiver
        $receiver = isset($_GET['receiver']) ? sanitize_text_field($_GET['receiver']) : '';
        echo '<label class="screen-reader-text" for="publishpress-notifications-filter-receiver">' . esc_html__('Filter by receiver', 'publishpress') . '</label>';
        echo '<input type="text" id="publishpress-notifications-filter-receiver" placeholder="' . esc_html__(
                'All Receivers',
                'publishpress'
            ) . '" name="receiver" value="' . esc_attr($receiver) . '" />';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo get_submit_button(esc_html__('Filter', 'publishpress'), 'secondary', 'submit', false);

        echo '</div>';
        echo '<br>';
    }
}
