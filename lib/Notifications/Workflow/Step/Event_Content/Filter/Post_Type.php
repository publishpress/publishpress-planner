<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Event_Content\Filter;

use PublishPress\Notifications\Workflow\Step\Event\Filter\Filter_Interface;
use PublishPress\Notifications\Workflow\Step\Event_Content\Post_Type as Step_Post_Type;

class Post_Type extends Base implements Filter_Interface
{
    const META_KEY_POST_TYPE = '_psppno_posttype';

    /**
     * Function to render and returnt the HTML markup for the
     * Field in the form.
     *
     * @return string
     */
    public function render()
    {
        echo $this->get_service('view')->render(
            'workflow_filter_multiple_select',
            [
                'name'    => esc_attr("publishpress_notif[{$this->step_name}_filters][post_type]"),
                'id'      => esc_attr("publishpress_notif_{$this->step_name}_filters_post_type"),
                'options' => $this->get_options(),
                'labels'  => [
                    'label' => esc_html__('Post Types', 'publishpress'),
                ],
            ]
        );
    }

    /**
     * Returns a list of post types in the options format
     *
     * @return array
     */
    protected function get_options()
    {
        $post_types = $this->get_post_types();
        $options    = [];
        $metadata   = (array)$this->get_metadata(static::META_KEY_POST_TYPE);

        foreach ($post_types as $slug => $label) {
            $options[] = [
                'value'    => $slug,
                'label'    => $label,
                'selected' => in_array($slug, $metadata),
            ];
        }

        return $options;
    }

    /**
     * Function to save the metadata from the metabox
     *
     * @param int $id
     * @param WP_Post $post
     */
    public function save_metabox_data($id, $post)
    {
        $filters = isset($_POST['publishpress_notif']["{$this->step_name}_filters"])
            && is_array($_POST['publishpress_notif']["{$this->step_name}_filters"])
            ? wp_unslash($_POST['publishpress_notif']["{$this->step_name}_filters"])
            : [];

        if (!isset($filters['post_type'])) {
            $values = [];
        } else {
            $values = array_map(
                'sanitize_key',
                (array)$filters['post_type']
            );
        }

        $this->update_metadata_array($id, static::META_KEY_POST_TYPE, $values);
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
    public function get_run_workflow_query_args($query_args, $event_args)
    {
        // If post is not set, we ignore.
        if (!isset($event_args['params']['post_id']) || !is_numeric($event_args['params']['post_id'])) {
            return parent::get_run_workflow_query_args($query_args, $event_args);
        }

        $post = get_post($event_args['params']['post_id']);

        // Add the filters
        $query_args['meta_query'][] = [
            'relation' => 'OR',
            // The filter is disabled
            [
                'key'     => Step_Post_Type::META_KEY_SELECTED,
                'value'   => '0',
                'compare' => '=',
            ],
            // The filter is disabled
            [
                'key'     => Step_Post_Type::META_KEY_SELECTED,
                'value'   => '',
                'compare' => '=',
            ],
            // The filter is disabled
            [
                'key'     => Step_Post_Type::META_KEY_SELECTED,
                'value'   => '',
                'compare' => 'IS NULL',
            ],
            // The filter wasn't set yet
            [
                'key'     => Step_Post_Type::META_KEY_SELECTED,
                'value'   => '',
                'compare' => 'NOT EXISTS',
            ],
            // The filter validates the value
            [
                'key'     => static::META_KEY_POST_TYPE,
                'value'   => $post->post_type,
                'type'    => 'CHAR',
                'compare' => '=',
            ],
        ];

        return parent::get_run_workflow_query_args($query_args, $event_args);
    }
}
