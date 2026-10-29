<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow;

use Exception;
use PublishPress\Notifications\Shortcodes;
use PublishPress\Notifications\Traits\Dependency_Injector;
use WP_Post;

class Workflow
{
    use Dependency_Injector;

    /**
     * The post of this workflow.
     *
     * @var WP_Post
     */
    public $workflow_post;

    /**
     * An array with arguments set by the action
     *
     * @var array
     */
    public $event_args;

    /**
     * @var Shortcodes
     */
    private $shortcodesHandler;

    /**
     * The constructor
     *
     * @param WP_Post $workflow_post
     */
    public function __construct($workflow_post)
    {
        $this->workflow_post = $workflow_post;
    }

    public static function load_by_id($workflowId)
    {
        $post = get_post((int)$workflowId);

        return new self($post);
    }

    /**
     * Runs this workflow without applying any filter. We assume it was
     * already filtered in the query.
     *
     * @param array $event_args
     *
     * @throws Exception
     */
    public function run($event_args)
    {
        if (!$this->is_enabled()) {
            return;
        }
        $this->event_args = $event_args;

        do_action('publishpress_notifications_running_for_post', $this);
    }

    public function is_enabled()
    {
        return get_post_meta($this->workflow_post->ID, '_psppno_enabled', true) !== '0';
    }

    private function get_receivers()
    {
        /**
         * Filters the list of receivers for the notification workflow.
         *
         * @param WP_Post $workflow
         * @param array $args
         */
        return apply_filters(
            'publishpress_notif_run_workflow_receivers',
            [],
            $this->workflow_post,
            $this->event_args
        );
    }

    public function get_option($option)
    {
        return apply_filters('publishpress_notif_workflow_option', null, $option, $this);
    }

    /**
     * Returns a list of receivers ids for this workflow
     *
     * @return array
     */
    public function get_receivers_by_channel()
    {
        $receivers = $this->get_receivers();

        $filtered_receivers = [];

        $optionSkipUser = (bool)$this->get_option('skip_user');

        if (!empty($receivers)) {
            // Classify receivers per channel, ignoring who has muted the channel.
            foreach ($receivers as $receiverData) {
                $receiver = $receiverData['receiver'];

                // Is an user (identified by the id)?
                if (is_numeric($receiver) || is_object($receiver)) {
                    // Try to extract the ID from the object
                    if (is_object($receiver)) {
                        if (isset($receiver->ID) && !empty($receiver->ID)) {
                            $receiver = $receiver->ID;
                        } else {
                            if (isset($receiver->id) && !empty($receiver->id)) {
                                $receiver = $receiver->id;
                            } else {
                                // If the object doesn't have an ID, we ignore it.
                                continue;
                            }
                        }
                    }

                    // Doesn't send the notification for the same user that triggered it if the option to skip is set.
                    if ($optionSkipUser && $receiver == $this->event_args['user_id']) {
                        continue;
                    }

                    $channel = get_user_meta($receiver, 'psppno_workflow_channel_' . $this->workflow_post->ID, true);

                    // If channel is empty, we set a default channel.
                    if (empty($channel)) {
                        $channel = apply_filters('psppno_default_channel', 'email', $this->workflow_post->ID);
                    }

                    // If the channel is "mute", we ignore this receiver.
                    if ('mute' === $channel) {
                        continue;
                    }

                    // Make sure the array for the channel is initialized.
                    if (!isset($filtered_receivers[$channel])) {
                        $filtered_receivers[$channel] = [];
                    }

                    // Add to the channel's list.
                    $filtered_receivers[$channel][] = $receiverData;
                } else {
                    // Check if it is an explicit email address.
                    if (isset($receiverData['channel'])) {
                        // Doesn't send the notification for the same user that triggered it if the option to skip is set.
                        if ($optionSkipUser && isset($receiverData['receiver'])) {
                            $user_id = email_exists($receiverData['receiver']);
                            if ($user_id == $this->event_args['user_id']) {
                                continue;
                            }
                        }

                        if (!isset($filtered_receivers[$receiverData['channel']])) {
                            $filtered_receivers[$receiverData['channel']] = [];
                        }

                        // Add to the email channel, without the "email:" prefix.
                        $filtered_receivers[$receiverData['channel']][] = $receiverData;
                    }
                }
            }
        }

        return $filtered_receivers;
    }

    /**
     * Returns a list of receivers ids for this workflow
     *
     * @return array
     */
    public function get_receivers_by_group()
    {
        $receivers = $this->get_receivers();

        $filtered_receivers = [];

        $optionSkipUser = (bool)$this->get_option('skip_user');

        if (!empty($receivers)) {
            // Classify receivers per channel, ignoring who has muted the channel.
            foreach ($receivers as $receiverData) {
                $receiver = $receiverData['receiver'];

                // Is an user (identified by the id)?
                if (is_numeric($receiver) || is_object($receiver)) {
                    // Try to extract the ID from the object
                    if (is_object($receiver)) {
                        if (isset($receiver->ID) && !empty($receiver->ID)) {
                            $receiver = $receiver->ID;
                        } else {
                            if (isset($receiver->id) && !empty($receiver->id)) {
                                $receiver = $receiver->id;
                            } else {
                                // If the object doesn't have an ID, we ignore it.
                                continue;
                            }
                        }
                    }

                    // Doesn't send the notification for the same user that triggered it if the option to skip is set.
                    if ($optionSkipUser && $receiver == $this->event_args['user_id']) {
                        continue;
                    }

                    $channel = get_user_meta($receiver, 'psppno_workflow_channel_' . $this->workflow_post->ID, true);

                    // If channel is empty, we set a default channel.
                    if (empty($channel)) {
                        $channel = apply_filters('psppno_default_channel', 'email', $this->workflow_post->ID);
                    }

                    // If the channel is "mute", we ignore this receiver.
                    if ('mute' === $channel) {
                        continue;
                    }

                    $receiverData['channel'] = $channel;

                    // Make sure the array for the channel is initialized.
                    if (!isset($filtered_receivers[$receiverData['group']])) {
                        $filtered_receivers[$receiverData['group']] = [];
                    }

                    // Add to the group's list.
                    $filtered_receivers[$receiverData['group']][] = $receiverData;
                } else {
                    // Doesn't send the notification for the same user that triggered it if the option to skip is set.
                    if ($optionSkipUser && isset($receiverData['receiver'])) {
                        $user_id = email_exists($receiverData['receiver']);
                        if ($user_id == $this->event_args['user_id']) {
                            continue;
                        }
                    }
                    if (!isset($filtered_receivers[$receiverData['group']])) {
                        $filtered_receivers[$receiverData['group']] = [];
                    }

                    $filtered_receivers[$receiverData['group']][] = $receiverData;
                }
            }
        }

        return $filtered_receivers;
    }

    /**
     * Returns the content for the notification, as an associative array with
     * the following keys:
     *     - subject
     *     - body
     *
     * @return string
     *
     * @throws Exception
     */
    public function get_content()
    {
        $content = ['subject' => '', 'body' => ''];

        /**
         * Filters the content for the notification workflow.
         *
         * @param WP_Post $workflow
         * @param array $args
         */
        $content = apply_filters(
            'publishpress_notif_run_workflow_content',
            $content,
            $this->workflow_post,
            $this->event_args
        );

        if (!array_key_exists('subject', $content)) {
            $content['subject'] = '';
        }

        if (!array_key_exists('body', $content)) {
            $content['body'] = '';
        }

        return $content;
    }

    /**
     * @param array $content
     * @param mixed $receiver
     * @param string $channel
     *
     * @return array
     */
    public function do_shortcodes_in_content($content, $receiver, $channel)
    {
        if (empty($this->shortcodesHandler)) {
            $this->shortcodesHandler = $this->get_service('shortcodes');
            $this->shortcodesHandler->register($this->workflow_post, $this->event_args);
        }

        /**
         * Action triggered before do shortcodes in the content.
         *
         * @param string $content
         * @param mixed $receiver
         * @param string $channel
         */
        do_action('publishpress_workflow_do_shortcode_in_content', $content, $receiver, $channel);

        // Replace placeholders in the subject and body
        $content['subject'] = do_shortcode($content['subject']);
        $content['body']    = do_shortcode($content['body']);

        return $content;
    }

    public function unregister_shortcodes()
    {
        $this->shortcodesHandler->unregister();
        $this->shortcodesHandler = null;
    }
}
