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
use PublishPress\Notifications\Workflow\Workflow;
use WP_Error;
use WP_Post;

class Email extends Base implements Channel_Interface
{
    const META_KEY_EMAIL = '_psppno_chnemail';

    private $emailFailures = [];

    /**
     * The constructor
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->name  = 'email';
        $this->label = __('Email', 'publishpress');
        $this->icon  = PUBLISHPRESS_URL . 'modules/improved-notifications/assets/img/icon-email.png';

        add_filter('publishpress_notif_error_log', [$this, 'filterErrorLog'], 10, 5);
        add_action('wp_mail_failed', [$this, 'emailFailed']);

        add_filter('publishpress_notifications_channel_icon_class', [$this, 'filterChannelIconClass']);
        add_filter('publishpress_notifications_receiver_address', [$this, 'filterReceiverAddress'], 10, 2);
        add_filter('publishpress_notifications_log_receiver_text', [$this, 'filterLogReceiverText'], 10, 2);

        parent::__construct();
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
        if (empty($receiverData['receiver'])) {
            return;
        }

        // Make sure we unserialize the content when it comes from async notifications.
        if (is_string($content)) {
            $content = maybe_unserialize($content);
        }

        $signature  = $this->get_notification_signature(
            $content,
            $channel . ':' . wp_json_encode($receiverData['receiver'])
        );
        $controller = $this->get_service('workflows_controller');

        // Check if the notification was already sent
        if ($controller->is_notification_signature_registered($signature)) {
            return;
        }

        // Send the emails
        $emailAddress = $this->get_receiver_email($receiverData['receiver']);

        $action       = 'transition_post_status' === $workflow->event_args['event'] ? 'status-change' : 'comment';

        $subject = html_entity_decode($content['subject']);

        $body = wpautop($content['body']);
        $body = apply_filters('publishpress_notifications_the_content', $body);
        $body = str_replace(']]>', ']]&gt;', $body);
        
        // Fix double http:// or https:// in URLs (e.g., href="http://http://example.com")
        $body = preg_replace('#(https?://)+(https?://)#i', '$2', $body);

        $attachments = [];
        if ($action === 'comment' 
            && isset($workflow->event_args['event']) 
            && $workflow->event_args['event'] === 'editorial_comment'
        ) {
            $comment_files = get_comment_meta($workflow->event_args['params']['comment_id'], '_pp_editorial_comment_files', true);
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
        }

        // Call the legacy notification module
        // Split the name and email, if set.
        $separatorPos = strpos($emailAddress, '/');
        if ($separatorPos > 0) {
            $emailAddress = substr($emailAddress, $separatorPos + 1, strlen($emailAddress));
        }

        $deliveryResult = $this->get_service('publishpress')->notifications->send_email(
            $action,
            $workflow->event_args,
            $subject,
            $body,
            '',
            $emailAddress,
            $attachments
        );

        /**
         * @param Workflow $workflow
         * @param string $channel
         * @param array $receiverData
         * @param string $subject
         * @param string $body
         * @param bool $deliveryResult
         * @param bool $async
         */
        do_action(
            'publishpress_notif_notification_sending',
            $workflow,
            $channel,
            $receiverData,
            $subject,
            $body,
            $deliveryResult[$emailAddress],
            $async
        );

        $controller->register_notification_signature($signature);
    }

    /**
     * Returns a list of the receivers' emails
     *
     * @param array $receiver
     *
     * @return string
     */
    protected function get_receiver_email($receiver)
    {
        if (!empty($receiver)) {
            // Check if we have the user ID or an email address
            if (is_numeric($receiver)) {
                $data     = $this->get_user_data($receiver);
                $receiver = $data->user_email;
            }
        }

        return sanitize_email($receiver);
    }

    /**
     * Filter the receivers organizing it by channel. Each channel get the list of receivers
     * and return
     *
     * @param array $receivers
     * @param WP_Post $workflow_post
     * @param array $event_args
     *
     * @return array
     */
    public function filter_receivers($receivers, $workflow_post, $event_args)
    {
        return $receivers;
    }

    /**
     * @param WP_Error $error
     */
    public function emailFailed($error)
    {
        if (isset($error->error_data['wp_mail_failed'])) {
            $emailData  = $error->error_data['wp_mail_failed'];
            $recipients = $emailData['to'];

            foreach ($recipients as $email) {
                if (is_object($email) && method_exists($email, 'getEmail')) {
                    $email = $email->getEmail();
                } elseif (is_object($email) && isset($email->email)) {
                    $email = $email->email;
                } elseif (is_array($email) && isset($email['email'])) {
                    $email = $email['email'];
                }

                $hash = $this->getEmailErrorHash($email, $emailData['subject'], $emailData['message']);

                $this->emailFailures[$hash] = $error->get_error_message();
            }
        }
    }

    /**
     * @param $receiver
     * @param $subject
     * @param $body
     *
     * @return string
     */
    private function getEmailErrorHash($receiver, $subject, $body)
    {
        $receiver = is_array($receiver) ? json_encode($receiver) : (string) $receiver;
        $subject  = is_array($subject) ? json_encode($subject) : (string) $subject;
        $body     = is_array($body) ? json_encode($body) : (string) $body;

        return md5(sprintf('%s:%s:%s', $receiver, $subject, $body));
    }

    /**
     * @param $result
     * @param $receiver
     * @param $subject
     * @param $body
     *
     * @return string
     */
    public function filterErrorLog($error, $result, $receiver, $subject, $body)
    {
        $hash = $this->getEmailErrorHash($receiver, $subject, $body);

        if (isset($this->emailFailures[$hash])) {
            $error = $this->emailFailures[$hash];
        }

        return $error;
    }

    public function filterChannelIconClass($channel)
    {
        if ($channel === 'email') {
            return 'dashicons dashicons-email';
        }

        return $channel;
    }

    public function filterReceiverAddress($receiverAddress, $channel)
    {
        if ('email' === $channel) {
            $receiverAddress = $this->get_receiver_email($receiverAddress);
        }

        return $receiverAddress;
    }

    public function filterLogReceiverText($receiverText, $receiverData)
    {
        if (!isset($receiverData['channel']) || $receiverData['channel'] !== $this->name || !isset($receiverData['receiver'])) {
            return $receiverText;
        }

        if (is_numeric($receiverData['receiver'])) {
            $user = get_user_by('ID', $receiverData['receiver']);

            if (!is_object($user)) {
                return $receiverText;
            }

            $receiverText = $user->user_nicename;
            $receiverText .= sprintf(
                '<span class="user-details muted">(user_id:%d, email:%s)</span>',
                $user->ID,
                $user->user_email
            );
        }

        return $receiverText;
    }
}
