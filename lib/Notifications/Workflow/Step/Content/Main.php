<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Content;

use PublishPress\Notifications\Workflow\Step\Base as Base_Step;

#[\AllowDynamicProperties]
class Main extends Base_Step
{
    const META_KEY_SUBJECT = '_psppno_contsubject';

    const META_KEY_BODY = '_psppno_contbody';

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->attr_prefix   = 'content';
        $this->view_name = 'workflow_content_main_field';
        $this->name          = 'main';
        $this->label         = __('Content', 'publishpress');

        parent::__construct();

        // Add the event filters to the metabox template
        add_filter(
            "publishpress_notif_workflow_metabox_context_{$this->attr_prefix}_{$this->name}",
            [$this, 'filter_workflow_metabox_context']
        );

        // Add the filter for the content in the workflow
        add_filter("publishpress_notif_run_workflow_content", [$this, 'filter_workflow_content'], 10, 3);
    }

    /**
     * Filters the context sent to the view template in the metabox
     *
     * @param array $template_context
     */
    public function filter_workflow_metabox_context($template_context)
    {
        $template_context['input_name'] = 'publishpress_notif[content_main]';
        $template_context['input_id']   = 'publishpress_notification_content_main_';
        $template_context['labels']     = [
            'subject' => __('Subject', 'publishpress'),
            'body'    => __('Body', 'publishpress'),
        ];

        $template_context['subject'] = esc_attr($this->get_metadata(static::META_KEY_SUBJECT, true));
        $template_context['body']    = $this->get_metadata(static::META_KEY_BODY, true);
        $template_context['nonce']   = wp_create_nonce('publishpress_notification_content');

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
        $content_main = [];

        if (isset($_POST['publishpress_notif']['content_main'])
            && is_array($_POST['publishpress_notif']['content_main'])) {
            $content_main = wp_unslash($_POST['publishpress_notif']['content_main']);
        } else {
            // Assume it is disabled
            update_post_meta($id, static::META_KEY_SUBJECT, false);
            update_post_meta($id, static::META_KEY_BODY, false);
        }

        // Sanitize the data
        $subject = isset($content_main['subject']) ? sanitize_text_field($content_main['subject']) : '';
        $body    = isset($content_main['body']) ? wp_kses_post($content_main['body']) : '';

        update_post_meta($id, static::META_KEY_SUBJECT, $subject);
        update_post_meta($id, static::META_KEY_BODY, $body);
    }

    /**
     * Filters the content for the workflow. Returns an associative array with
     * the subject and body.
     *
     * @param array $content
     * @param WP_Post $workflow
     * @param array $args
     *
     * @return array
     */
    public function filter_workflow_content($content, $workflow, $args)
    {
        $content['subject'] = get_post_meta($workflow->ID, static::META_KEY_SUBJECT, true);
        $content['body']    = get_post_meta($workflow->ID, static::META_KEY_BODY, true);

        return $content;
    }
}
