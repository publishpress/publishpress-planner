<?php
namespace PublishPress\Notifications\Workflow\Step\Event_Content\Filter;

class Posts extends Base
{
    const META_KEY_POSTS = '_psppno_specific_posts';

    public function render()
    {
        $this->get_service('view')->display('workflow_filter_posts', [
            'name' => 'publishpress_notif[' . $this->step_name . '_filters][post_ids]',
            'value' => implode(', ', (array) $this->get_metadata(static::META_KEY_POSTS)),
        ]);
    }

    public function save_metabox_data($id, $post)
    {
        if (!isset($_POST['publishpress_notif_metabox_events_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['publishpress_notif_metabox_events_nonce'])), 'publishpress_notif_save_metabox')) {
            return;
        }
        $value = isset($_POST['publishpress_notif'][$this->step_name . '_filters']['post_ids'])
            ? sanitize_text_field(wp_unslash($_POST['publishpress_notif'][$this->step_name . '_filters']['post_ids'])) : '';
        $ids = array_filter(wp_parse_id_list($value), static function ($post_id) {
            return current_user_can('edit_post', $post_id);
        });
        $this->update_metadata_array($id, static::META_KEY_POSTS, $ids);
    }

    public function get_run_workflow_query_args($query_args, $event_args)
    {
        $query_args['meta_query'][] = [
            'relation' => 'OR',
            ['key' => \PublishPress\Notifications\Workflow\Step\Event_Content\Posts::META_KEY_SELECTED, 'compare' => 'NOT EXISTS'],
            ['key' => \PublishPress\Notifications\Workflow\Step\Event_Content\Posts::META_KEY_SELECTED, 'value' => '1', 'compare' => '!='],
            ['key' => static::META_KEY_POSTS, 'value' => (int) ($event_args['params']['post_id'] ?? 0), 'compare' => '=', 'type' => 'NUMERIC'],
        ];
        return $query_args;
    }
}
