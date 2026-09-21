<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow;

use WP_Query;

class WorkflowsController
{
    /**
     * Store the signatures of sent notifications, usually to avoid duplicated notifications
     * to the same channel.
     *
     * @var array
     */
    protected $sent_notification_signatures = [];

    /**
     * The constructor
     */
    public function __construct()
    {
        add_action('publishpress_notifications_trigger_workflows', [$this, 'trigger_workflows']);
    }

    /**
     * Look for enabled workflows, filtering and running according to each settings.
     *
     * $params = [
     *     'event',
     *      'params' = [
     *          'post_id',
     *          'new_status',
     *          'old_status',
     *          'comment_id',
     *      ]
     * ]
     *
     * @param string $event
     * @param array $params
     */
    public function trigger_workflows($params)
    {
        try {
            $query = $this->get_workflows_filter_query($params);

            if (!empty($query->posts)) {
                foreach (apply_filters('publishpress_post_notification_trigger_workflows', $query->posts) as $workflowPost) {
                    // Run the workflow only if retrieved post has postmeta matching the current event. Note that each event has a different postmeta key.
                    if (!empty($params['event_key']) && get_post_meta($workflowPost->ID, $params['event_key'], true)) {
                        $workflow = new Workflow($workflowPost);
                        $workflow->run($params);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log(
                sprintf(
                    '[PublishPress Notifications] Exception: %s, %s',
                    $e->getMessage(),
                    __METHOD__
                )
            );
        }
    }

    /**
     * Returns a list of published workflows which passed all filters.
     *
     * $args = [
     *     'post',
     *     'new_status',
     *     'old_status',
     * ]
     *
     * @param array $params
     *
     * @return WP_Query
     */
    public function get_workflows_filter_query($params)
    {
        global $wpdb;

        // Build the query
        $query_args = [
            'nopaging'               => true,
            'post_type'              => PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW,
            'post_status'            => 'publish',
            'no_found_rows'          => true,
            'cache_results'          => true,
            'update_post_term_cache' => false,
            'meta_query'             => [],
            'needs_event_filter'     => true,
        ];

        /**
         * Filters the arguments sent to the query to get workflows and
         * each step's filters.
         *
         * @param array $query_args
         * @param array $params
         */
        $query_args = apply_filters('publishpress_notifications_running_workflow_meta_query', $query_args, $params);

        if (empty($query_args['meta_query']) || isset($query_args['needs_event_filter'])) {
            return (object) ['posts' => []];
        }

        $wpdb->query('SET SESSION SQL_BIG_SELECTS=1'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return new WP_Query($query_args);
    }

    /**
     * Loads instantiating the classes for the workflow steps.
     */
    public function load_workflow_steps()
    {
        // Options
        $classes_options = [
            '\\PublishPress\\Notifications\\Workflow\\Option\\SkipUser',
        ];
        /**
         * Filters the list of classes to define workflow "options"
         *
         * @param array $classes The list of classes to be loaded
         */
        $classes_options = apply_filters('publishpress_notif_workflow_options_classes', $classes_options);

        // When
        $classes_event = [
            '\\PublishPress\\Notifications\\Workflow\\Step\\Event\\Editorial_Comment',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Event\\Post_Update',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Event\\Post_TaxonomyUpdate',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Event\\Post_StatusTransition',
        ];
        /**
         * Filters the list of classes to define workflow "when" steps.
         *
         * @param array $classes The list of classes to be loaded
         */
        $classes_event = apply_filters('publishpress_notif_workflow_steps_event', $classes_event);

        // Which Content
        $classes_event_content = [
            '\\PublishPress\\Notifications\\Workflow\\Step\\Event_Content\\Post_Type',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Event_Content\\Category',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Event_Content\\Taxonomy',
        ];
        /**
         * Filters the list of classes to define workflow "when - which content" steps.
         *
         * @param array $classes The list of classes to be loaded
         */
        $classes_event_content = apply_filters(
            'publishpress_notif_workflow_steps_event_content',
            $classes_event_content
        );

        // Who
        $classes_receiver = [
            '\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\Site_Admin',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\Author',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\RevisionAuthor',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\User',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\Role',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\Group',
            '\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\Follower',
        ];

        if (!defined('PRESSPERMIT_VERSION')) {
            $classes_receiver = array_diff($classes_receiver, ['\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\Group']);
        }

        if (!defined('PUBLISHPRESS_REVISIONS_PRO_VERSION') || version_compare(PUBLISHPRESS_REVISIONS_PRO_VERSION, '3.6.3-rc3', '<')) {
            $classes_receiver = array_diff($classes_receiver, ['\\PublishPress\\Notifications\\Workflow\\Step\\Receiver\\RevisionAuthor']);
        }

        /**
         * Filters the list of classes to define workflow "who" steps.
         *
         * @param array $classes The list of classes to be loaded
         */
        $classes_receiver = apply_filters('publishpress_notif_workflow_steps_receiver', $classes_receiver);

        // Where
        $classes_channel = [
            '\\PublishPress\\Notifications\\Workflow\\Step\\Channel\\Email',
        ];
        /**
         * Filters the list of classes to define workflow "where" steps.
         *
         * @param array $classes The list of classes to be loaded
         */
        $classes_channel = apply_filters('publishpress_notif_workflow_steps_channel', $classes_channel);

        // What
        $classes_content = [
            '\\PublishPress\\Notifications\\Workflow\\Step\\Content\\Main',
        ];
        /**
         * Filters the list of classes to define workflow "what" steps.
         *
         * @param array $classes The list of classes to be loaded
         */
        $classes_content = apply_filters('publishpress_notif_workflow_steps_content', $classes_content);

        // Actions
        $classes_action = [
            '\\PublishPress\\Notifications\\Workflow\\Step\\Action\\Notification',
        ];
        /**
         * Filters the list of classes to define workflow "action" steps.
         *
         * @param array $classes The list of classes to be loaded
         */
        $classes_action = apply_filters('publishpress_notif_workflow_steps_action', $classes_action);


        $classes = array_merge(
            $classes_options,
            $classes_event,
            $classes_event_content,
            $classes_receiver,
            $classes_channel,
            $classes_content,
            $classes_action
        );

        // Instantiate each class
        foreach ($classes as $class) {
            if (class_exists($class)) {
                new $class;
            }
        }
    }

    /**
     * @param $signature
     */
    public function register_notification_signature($signature)
    {
        $this->sent_notification_signatures[$signature] = true;
    }

    /**
     * @param $signature
     *
     * @return bool
     */
    public function is_notification_signature_registered($signature)
    {
        $found = array_key_exists($signature, $this->sent_notification_signatures);

        return $found;
    }

    public function get_filtered_workflows($args)
    {
        $workflows = [];

        // Build the query
        $query_args = [
            'nopaging'      => true,
            'post_type'     => PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW,
            'post_status'   => 'publish',
            'no_found_rows' => true,
            'cache_results' => true,
            'meta_query'    => [],
        ];

        /**
         * Filters the arguments sent to the query to get workflows and
         * each step's filters.
         *
         * @param array $query_args
         * @param array $args
         */
        $query_args = apply_filters('publishpress_notifications_running_workflow_meta_query', $query_args, $args);

        $query = new WP_Query($query_args);

        if (!empty($query->posts)) {
            foreach ($query->posts as $post) {
                $workflows[] = new Workflow($post);
            }
        }

        return $workflows;
    }
}
