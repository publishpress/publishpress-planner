<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Action;

use Exception;
use PublishPress\Notifications\Helper;
use PublishPress\Notifications\Workflow\Workflow;

class Notification
{
    const DEFAULT_DUPLICATED_NOTIFICATION_THRESHOLD_IN_MINUTES = 10;

    const DEFAULT_DELAY_FOR_SENDING_NOTIFICATION_IN_SECONDS = 5;

    const DEFAULT_ROUND_FACTOR_FOR_NOTIFICATION_IN_SECONDS = 10;

    public function __construct()
    {
        add_action('publishpress_notifications_running_for_post', [$this, 'send_sync_notifications']);
        add_action('publishpress_notifications_send_notifications_action', [$this, 'send_notifications'], 10, 2);
    }

    public function send_sync_notifications($workflow)
    {
        /**
         * @param bool $stop
         * @param array $workflow
         */
        if (apply_filters('publishpress_notifications_stop_sync_notifications', false, $workflow)) {
            return;
        }

        $this->send_notifications($workflow);
    }

    /**
     * @param Workflow $workflow
     * @param bool $async
     * @throws Exception
     */
    public function send_notifications($workflow, $async = false)
    {
        if (!$workflow->is_enabled()) {
            return;
        }
        // Who will receive the notification?
        $receivers = $workflow->get_receivers_by_channel();

        // If we don't have receivers, abort the workflow.
        if (empty($receivers)) {
            return;
        }

        /*
         * What will the notification says?
         */
        $content_template = $workflow->get_content();

        // Run the action to each receiver.
        foreach ($receivers as $channel => $channel_receivers) {
            foreach ($channel_receivers as $receiver) {
                /**
                 * Prepare the content replacing shortcodes.
                 */
                $content = $workflow->do_shortcodes_in_content($content_template, $receiver['receiver'], $channel);

                /**
                 * @param
                 */
                $receiverAddress = apply_filters(
                    'publishpress_notifications_receiver_address',
                    $receiver['receiver'],
                    $channel
                );

                $threshold = Helper::getDuplicatedNotificationThreshold();

                // Check if this is a duplicated notification and skip it.
                // I hope this is a temporary fix. When scheduled, some notifications seems to be triggered multiple times
                // by the same cron task.
                if (Helper::isDuplicatedNotification($content, $receiverAddress, $channel, $threshold)) {
                    do_action(
                        'publishpress_notifications_skipped_duplicated',
                        $workflow,
                        $receiver,
                        $content,
                        $channel,
                        $async,
                        $threshold
                    );
                    continue;
                }

                /**
                 * Triggers the notification. This can be caught by notification channels.
                 * But can be intercepted by other plugins (cache, async, etc) to change the
                 * workflow.
                 *
                 * @param Workflow $workflow
                 * @param array $receiver
                 * @param array $content
                 * @param string $channel
                 * @param bool $async
                 */
                do_action(
                    'publishpress_notif_send_notification_' . $channel,
                    $workflow,
                    $receiver,
                    $content,
                    $channel,
                    $async
                );
            }
        }

        // Remove the shortcodes.
        $workflow->unregister_shortcodes();
    }
}
