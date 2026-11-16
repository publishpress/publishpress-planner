<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Traits;

trait Metadata
{
    /**
     * Returns the metadata of the current post.
     *
     * @param string $meta_key
     * @param bool $single
     *
     * @return mixed
     */
    public function get_metadata($meta_key, $single = false)
    {
        global $post;

        return get_post_meta($post->ID, $meta_key, $single);
    }

    /**
     * Updates the metadata for the current post using an array as input.
     *
     * @param int $post_id
     * @param string $meta_key
     * @param array $meta_value
     * @param bool $all_if_empty
     */
    public function update_metadata_array($post_id, $meta_key, $meta_value = [])
    {
        // Cleanup the metadata
        $this->delete_metadata($meta_key, '', $post_id);

        if (!empty($meta_value)) {
            foreach ($meta_value as $value) {
                add_post_meta($post_id, $meta_key, $value);
            }
        }
    }

    /**
     * Deletes the metadata for the current post. If not single, set the value.
     *
     * @param string $meta_key
     * @param mixed $meta_value
     *
     * @return mixed
     */
    public function delete_metadata($meta_key, $meta_value = '', $id = null)
    {
        global $post;

        if (empty($id)) {
            $id = $post->ID;
        }

        return delete_post_meta($id, $meta_key, $meta_value);
    }
}
