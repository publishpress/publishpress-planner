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
        $value = 0;
        if (isset($_POST[$this->getFieldName()])) {
            $value = sanitize_key(wp_unslash($_POST[$this->getFieldName()]));
        }

        update_post_meta(
            $postId,
            $this->getName(),
            $value
        );
    }
}
