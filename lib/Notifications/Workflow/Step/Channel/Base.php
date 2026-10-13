<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Channel;

use Exception;
use PublishPress\Notifications\Workflow\Step\Base as Base_Step;
use WP_User;

#[\AllowDynamicProperties]
class Base extends Base_Step
{
    const META_KEY_EMAIL = '_psppno_chnbase';

    /**
     * @var string
     */
    protected $icon;

    /**
     * The constructor
     *
     * @throws Exception
     */
    public function __construct()
    {
        if (empty($this->attr_prefix)) {
            $this->attr_prefix = 'channel';
        }

        if (empty($this->view_name)) {
            $this->view_name = 'workflow_channel_field';
        }

        if (empty($this->name)) {
            throw new Exception("Channel name not defined");
        }

        if (empty($this->label)) {
            throw new Exception("Channel label not defined");
        }

        parent::__construct();

        // Add filter to display the channel in the user's profile
        add_filter('psppno_filter_channels_user_profile', [$this, 'filter_channel']);
        add_filter('psppno_filter_channels', [$this, 'filter_channel']);

        // Hook to the notification action
        add_action('publishpress_notif_send_notification_' . $this->name, [$this, 'action_send_notification'], 10, 5);

        // Check if we can hook to the psppno_save_user_profile action
        add_action('psppno_save_user_profile', [$this, 'action_save_user_profile']);

        add_filter('publishpress_notification_channel', [$this, 'channelLabel']);
    }

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
     * @param array $channels
     *
     * @return array
     *
     * @deprecated
     */
    public function filter_channel_user_profile($channels)
    {
        return $this->filter_channel($channels);
    }

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
     * @param array $channels
     *
     * @return array
     */
    public function filter_channel($channels)
    {
        $channels[] = (object)[
            'name'    => $this->name,
            'label'   => $this->label,
            'options' => $this->get_user_profile_option_fields(),
            'icon'    => $this->icon,
        ];

        return $channels;
    }

    /**
     * Returns a list of option fields to display in the user profile.
     *
     * 'options': [
     *     [
     *         'name'
     *         'html'
     *     ]
     *  ]
     *
     * @return array
     */
    protected function get_user_profile_option_fields()
    {
        return [];
    }

    /**
     * Renders the field in the metabox. On this case, we do not print
     * anything for now.
     *
     * @param string $html
     *
     * @return string
     */
    public function render_metabox_section($html)
    {
        return $html;
    }

    /**
     * Action hooked when the user profile is saved
     *
     * @param int $user_id
     */
    public function action_save_user_profile($user_id)
    {
        return;
    }

    /**
     * Check if this channel is selected and triggers the notification.
     *
     * @param Workflow $workflow
     * @param array $receiverData
     * @param array $content
     * @param string $channel
     * @param bool $async
     *
     * @throws Exception
     */
    public function action_send_notification($workflow, $receiverData, $content, $channel, $async)
    {
        return;
    }

    public function channelLabel($channel)
    {
        if ($channel === $this->name) {
            $channel = $this->label;
        }

        return $channel;
    }

    /**
     * Returns the user's data, by the user id.
     *
     * @param int $user_id
     *
     * @return WP_User
     */
    protected function get_user_data($user_id)
    {
        return get_userdata($user_id);
    }

    /**
     * @param $content
     * @param $channel
     *
     * @return string
     */
    protected function get_notification_signature($content, $channel)
    {
        $signature = md5(wp_json_encode($content) . '|' . $channel);

        return $signature;
    }
}
