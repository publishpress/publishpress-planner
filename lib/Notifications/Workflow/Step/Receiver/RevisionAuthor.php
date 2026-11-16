<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2026 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Receiver;

class RevisionAuthor extends Simple_Checkbox implements Receiver_Interface
{
    const META_KEY = '_psppno_to_revision_author';

    const META_VALUE = 'revision_author';

    protected $option = 'receiver_revision_author';

    /**
     * The constructor
     */
    public function __construct()
    {
        global $post;

        if (is_admin() && !empty($post) && ('psppnotif_workflow' == $post->post_type)) {
            // Don't offer Revision Author option for standard Planner notifications
            $excludes = ['new-post-is-created-in-draft-status', 'new-post-is-published', 'notify-when-content-is-published', 'existing-post-is-updated', 'notify-on-editorial-comments'];

            foreach ($excludes as $exclude_notif) {
                if (0 === strpos($post->post_name, $exclude_notif)) {
                    return;
                }
            }
        }

        $this->name        = 'revision_author';
        $this->label       = __('Author of the revision', 'publishpress');
        $this->option_name = 'receiver_revision_author';

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
        if ($this->is_selected($workflow->ID) && !empty($args['params']['revision_id'])) {
            $post = get_post($args['params']['revision_id']);

            /**
             * @param int $post_author
             * @param int $post_id
             *
             * @return int|array
             */
            $post_authors = apply_filters(
                'publishpress_notifications_receiver_revision_author',
                [$post->post_author],
                $workflow->ID,
                $args
            );

            if (!is_array($post_authors)) {
                $post_authors = [$post_authors];
            }

            foreach ($post_authors as $post_author) {
                $receiverData = [
                    'receiver' => $post_author,
                    'group'    => self::META_VALUE
                ];

                if (!is_numeric($post_author) && substr_count($post_author, '@') > 0) {
                    $receiverData['channel'] = 'email';
                }

                $receivers[] = $receiverData;
            }
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
            $values[] = __('Revision Author', 'publishpress');
        }

        return $values;
    }
}
