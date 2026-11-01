<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Option;

class SkipUser extends OptionCheckboxAbstract
{
    protected function getName()
    {
        return '_psppno_option_skip_user';
    }

    protected function getFieldName()
    {
        return 'publishpress_notif_option_skip_user';
    }

    protected function getLabel()
    {
        return esc_html__('Skip current user', 'publishpress');
    }

    protected function getDescription()
    {
        return esc_html__('Skip notifications for the user who triggered the action', 'publishpress');
    }

    protected function getValue()
    {
        return (bool)get_post_meta($this->post->ID, $this->getName(), true);
    }

    public function setValue($postId, $post)
    {
        if (!isset($_POST['publishpress_notif_metabox_events_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['publishpress_notif_metabox_events_nonce'])), 'publishpress_notif_save_metabox')) {
            return;
        }
        return $this->save_values($postId, $post, wp_unslash($_POST), true);
    }

    /** Persist unslashed native workflow values independently of a browser form. */
    public function save_values($postId, $post, array $data, $fromForm = false)
    {

        $value = 0;
        if (isset($data[$this->getFieldName()])) {
            $value = sanitize_key($data[$this->getFieldName()]);
        }

        update_post_meta(
            $postId,
            $this->getName(),
            $value
        );

    }
}
