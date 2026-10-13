<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Event;

use PublishPress\Notifications\Traits\Metadata;
use PublishPress\Notifications\Workflow\Step\Base as Base_Step;

#[\AllowDynamicProperties]
class Base extends Base_Step
{
    use Metadata;

    const META_KEY_SELECTED = '_psppno_evtundefined';

    const META_VALUE_SELECTED = 'undefined';

    /**
     * The constructor
     */
    public function __construct()
    {
        if ('base' === $this->attr_prefix) {
            $this->attr_prefix = 'event';
        }

        $this->view_name = 'workflow_event_field';

        parent::__construct();

        // Add the event filters to the metabox template
        add_filter(
            "publishpress_notif_workflow_metabox_context_{$this->attr_prefix}_{$this->name}",
            [$this, 'filter_workflow_metabox_context']
        );

        // Add the filter to the run workflow query args
        add_filter(
            'publishpress_notifications_running_workflow_meta_query',
            [$this, 'filter_running_workflow_query_args'],
            10,
            2
        );
    }

    /**
     * Filters the context sent to the view template in the metabox
     *
     * @param array $template_context
     */
    public function filter_workflow_metabox_context($template_context)
    {
        $template_context['name']          = esc_attr("publishpress_notif[{$this->attr_prefix}][]");
        $template_context['id']            = esc_attr("publishpress_notif_{$this->attr_prefix}_{$this->name}");
        $template_context['event_filters'] = $this->get_filters();

        $meta = (int)$this->get_metadata(static::META_KEY_SELECTED, true);

        $template_context['meta'] = [
            'selected' => (bool)$meta,
        ];

        return $template_context;
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

        if (!isset($publishpress_notif[$this->attr_prefix])) {
            // Assume it is disabled
            update_post_meta($id, static::META_KEY_SELECTED, false);
        }

        $params = $this->sanitize_metabox_data($publishpress_notif);

        if (isset($params[$this->attr_prefix])) {
            // Is selected in the events?
            $selected = in_array(static::META_VALUE_SELECTED, $params[$this->attr_prefix]);
            update_post_meta($id, static::META_KEY_SELECTED, $selected);
        }

        // Process the filters
        $filters = $this->get_filters();
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                $filter->save_metabox_data($id, $post);
            }
        }
    }

    protected function sanitize_metabox_data($data)
    {
        if (isset($data[$this->attr_prefix])) {
            $data[$this->attr_prefix] = array_values($data[$this->attr_prefix]);
            $data[$this->attr_prefix] = array_map('sanitize_key', $data[$this->attr_prefix]);
        }

        // Additional sanitization is made inside the Filter classes.
        return $data;
    }

    /**
     * Filters and returns the arguments for the query which locates
     * workflows that should be executed.
     *
     * @param array $query_args
     * @param array $event_args
     *
     * @return array
     */
    public function filter_running_workflow_query_args($query_args, $event_args)
    {
        return $query_args;
    }

    /**
     * Add the metakey to the array to be processed
     *
     * @param array $metakeys
     *
     * @return array
     */
    public function filter_events_metakeys($metakeys)
    {
        $metakeys[static::META_KEY_SELECTED] = $this->label;

        return $metakeys;
    }

    /**
     * @param $event_args
     *
     * @return bool
     */
    protected function should_ignore_event_on_query($event_args)
    {
        return (isset($event_args['params']['ignore_event']) && $event_args['params']['ignore_event'] == true);
    }
}
