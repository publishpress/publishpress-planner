<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Receiver;

class Simple_Checkbox extends Base implements Receiver_Interface
{
    const META_KEY = '_psppno_to_______';

    const META_VALUE = 'define';

    protected $option_name = 'define-option';

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->view_name = 'workflow_receiver_checkbox_field';

        parent::__construct();
    }

    /**
     * Filters the context sent to the view template in the metabox
     *
     * @param array $template_context
     */
    public function filter_workflow_metabox_context($template_context)
    {
        // Metadata
        $meta = $this->get_metadata(static::META_KEY, true);

        $template_context['meta'] = [
            'selected' => (bool)$meta,
        ];

        return $template_context;
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
        return $receivers;
    }

    /**
     * Method called when a notification workflow is saved.
     *
     * @param int $id
     * @param WP_Post $post
     */
    public function save_metabox_data($id, $post)
    {
        $publishpress_notif = isset($_POST['publishpress_notif']) && is_array($_POST['publishpress_notif'])
            ? wp_unslash($_POST['publishpress_notif'])
            : [];

        $selected = isset($publishpress_notif[$this->option_name])
            && $publishpress_notif[$this->option_name] === static::META_VALUE;

        $this->set_selection($id, $selected);
    }

    /**
     * Update the meta data to set the selection for the give workflow
     *
     * @param int $post_id
     * @param bool $selected
     */
    protected function set_selection($post_id, $selected)
    {
        update_post_meta($post_id, static::META_KEY, $selected);
    }

    /**
     * Returns true if the receiver is selected in the respective workflow.
     *
     * @param int $post_id
     *
     * @return bool
     */
    protected function is_selected($post_id)
    {
        return (bool)get_post_meta($post_id, static::META_KEY, true);
    }
}
