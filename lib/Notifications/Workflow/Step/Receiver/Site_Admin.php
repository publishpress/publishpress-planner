<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Receiver;

class Site_Admin extends Simple_Checkbox implements Receiver_Interface
{
    const META_KEY = '_psppno_tositeadmin';

    const META_VALUE = 'site_admin';

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->name        = 'site_admin';
        $this->label       = __('Site Administrator', 'publishpress');
        $this->option_name = 'receiver_site_admin';

        parent::__construct();
    }

    /**
     * Filters the list of receivers for the workflow. Returns the list of IDs.
     *
     * @param array $receivers
     * @param WP_Post $workflow
     * @param array $args
     *
     * @return array
     */
    public function filter_workflow_receivers($receivers, $workflow, $args)
    {
        // If checked, add the authors to the list of receivers
        if ($this->is_selected($workflow->ID)) {
            $receivers[] = [
                'receiver' => get_bloginfo('admin_email'),
                'channel'  => 'email',
                'group'    => self::META_VALUE
            ];

            /**
             * Filters the list of receivers, but triggers only when the site admin is selected.
             *
             * @param array $receivers
             * @param WP_Post $workflow
             * @param array $args
             */
            $receivers = apply_filters('publishpress_notif_workflow_receiver_site_admin', $receivers, $workflow, $args);
        }

        return $receivers;
    }

    /**
     * Add the respective value to the column in the workflow list
     *
     * @param array $values
     * @param int $post_id
     *
     * @return array
     */
    public function filter_receivers_column_value($values, $post_id)
    {
        if ($this->is_selected($post_id)) {
            $values[] = __('Site Administrator', 'publishpress');
        }

        return $values;
    }
}
