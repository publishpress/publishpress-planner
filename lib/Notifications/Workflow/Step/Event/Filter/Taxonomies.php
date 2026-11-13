<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Event\Filter;

use PP_Notifications;
use PublishPress\Notifications\Workflow\Step\Event\Post_TaxonomyUpdate;

class Taxonomies extends Base implements Filter_Interface
{
    const META_KEY_TAXONOMIES_FROM = '_psppno_taxonomiesfrom';

    /**
     * Function to render and returnt the HTML markup for the
     * Field in the form.
     *
     * @return string
     */
    public function render()
    {
        $this->get_service('view')->display(
            'workflow_filter_multiple_select',
            [
                'name'         => esc_attr("publishpress_notif[{$this->step_name}_filters][taxonomies]"),
                'id'           => esc_attr("publishpress_notif_{$this->step_name}_filters_taxonomies"),
                'options'      => $this->get_options(),
                'labels'       => [
                    'label' => esc_html__('Taxonomies', 'publishpress'),
                ],
            ]
        );
    }

    /**
     * Returns a list of taxonomies in the options format
     *
     * @return array
     */
    protected function get_options()
    {

        $taxonomies = \PP_Notifications::getWorkflowTaxonomies();
        $metadata = (array)$this->get_metadata(static::META_KEY_TAXONOMIES_FROM);
        $options  = [];

        foreach ($taxonomies as $tax) {
            if (empty($tax->labels->name)) {
                continue;
            }
            $options[] = [
                'value'    => esc_attr($tax->name),
                'label'    => esc_html($tax->labels->name. ' ('.$tax->name.')'),
                'selected' => in_array($tax->name, $metadata),
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
        if (!isset($_POST['publishpress_notif_metabox_events_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['publishpress_notif_metabox_events_nonce'])), 'publishpress_notif_save_metabox')) {
            return;
        }
        return $this->save_values($id, $post, wp_unslash($_POST), true);
    }

    /** Persist unslashed native workflow values independently of a browser form. */
    public function save_values($id, $post, array $data, $fromForm = false)
    {

        if (!isset($data['publishpress_notif']["{$this->step_name}_filters"]['taxonomies'])) {
            $values = [];
        } else {
            $values = array_map(
                'sanitize_key',
                (array)$data['publishpress_notif']["{$this->step_name}_filters"]['taxonomies']
            );
        }

        $this->update_metadata_array($id, static::META_KEY_TAXONOMIES_FROM, $values);

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
        // Taxonomy
        $query_args['meta_query'][] = [
            [
                'key'     => static::META_KEY_TAXONOMIES_FROM,
                'value'   => $event_args['params']['taxonomy'],
                'compare' => '=',
            ],
        ];

        return parent::get_run_workflow_query_args($query_args, $event_args);
    }
}
