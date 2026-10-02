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

namespace PublishPress\AsyncNotifications;

use Exception;
use PublishPress\Notifications\Helper;
use PublishPress\Notifications\Traits\Dependency_Injector;
use PublishPress\Notifications\Workflow\Step\Action\Notification;

/**
 * Class DBAdapter
 *
 * @package PublishPress\AsyncNotifications
 */
class WPCronAdapter implements SchedulerInterface
{
    use Dependency_Injector;

    const SEND_NOTIFICATION_HOOK = 'publishpress_notifications_send_notification';

    /**
     * Schedule the notification for async processing.
     *
     * @param $workflowPostId
     * @param $eventArgs
     *
     * @throws Exception
     */
    public function scheduleNotification($workflowPostId, $eventArgs)
    {
        $data = [
            'workflow_id' => $workflowPostId,
            'event_args' => $eventArgs,
        ];

        $delay = (int) apply_filters(
            'publishpress_notifications_schedule_delay_in_seconds',
            Notification::DEFAULT_DELAY_FOR_SENDING_NOTIFICATION_IN_SECONDS
        );
        $roundFactor = (int) apply_filters(
            'publishpress_notifications_schedule_round_factor_in_seconds',
            Notification::DEFAULT_ROUND_FACTOR_FOR_NOTIFICATION_IN_SECONDS
        );

        // We use a round factor for stopping multiple notifications with the same content
        $now  = time();
        $time = $now + max(0, $delay);

        if ($roundFactor > 1) {
            $remainder = $time % $roundFactor;

            if ($remainder > 0) {
                $time += $roundFactor - $remainder;
            }
        }

        $time = max($time, $now + max(0, $delay));

        if (Helper::isDuplicatedNotificationSchedule($time, $data)) {
            return;
        }

        /**
         * @param array $data
         */
        $data = apply_filters('publishpress_notifications_scheduled_data', $data);

        $timestamp = apply_filters(
            'publishpress_notifications_scheduled_time_for_notification',
            $time,
            $workflowPostId,
            $eventArgs['params']['post_id']
        );

        if (false === $timestamp) {
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                // phpcs:disable WordPress.PHP.DevelopmentFunctions
                error_log('PublishPress aborted a notification. Invalid timestamp for the workflow ' . $workflowPostId);
                // phpcs:enable
            }

            return;
        }

        $cronId = $this->getCronId($data);
        do_action('publishpress_notifications_scheduled_cron_task', $data, $cronId);

        $data['result'] = $this->scheduleEventInTheCron($data, $timestamp);

        /**
         * @param array $data
         */
        do_action('publishpress_notifications_scheduled_notification', $data);
    }

    private function getCronId($args)
    {
        return md5(maybe_serialize([$args]));
    }

    /**
     * Schedule the notification event.
     *
     * @param $args
     * @param $timestamp
     *
     * @return bool
     * @throws Exception
     *
     */
    private function scheduleEventInTheCron($args, $timestamp)
    {
        return wp_schedule_single_event(
            $timestamp,
            self::SEND_NOTIFICATION_HOOK,
            [$args]
        );
    }
}
