<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Receiver;

class Author extends Simple_Checkbox implements Receiver_Interface
{
    const META_KEY = '_psppno_toauthor';

    const META_VALUE = 'author';

    protected $option = 'receiver_author';

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->name        = 'author';
        $this->label       = __('Authors of the content', 'publishpress');
        $this->option_name = 'receiver_author';

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
            $post = get_post($args['params']['post_id']);

            if (empty($post) || is_wp_error($post)) {
                return $receivers;
            }

            /**
             * @param int $post_author
             * @param int $post_id
             *
             * @return int|array
             */
            $post_authors = apply_filters(
                'publishpress_notifications_receiver_post_authors',
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
            $values[] = __('Authors', 'publishpress');
        }

        return $values;
    }
}
