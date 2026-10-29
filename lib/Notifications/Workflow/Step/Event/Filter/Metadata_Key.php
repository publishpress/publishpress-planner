<?php
namespace PublishPress\Notifications\Workflow\Step\Event\Filter;

class Metadata_Key extends Base
{
    const META_KEY = '_psppno_metadata_key';

    public function render()
    {
        $this->get_service('view')->display('workflow_filter_metadata_key', [
            'name' => 'publishpress_notif[' . $this->step_name . '_filters][meta_key]',
            'value' => $this->get_metadata(static::META_KEY, true),
        ]);
    }

    public function save_metabox_data($id, $post)
    {
        if (!isset($_POST['publishpress_notif_metabox_events_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['publishpress_notif_metabox_events_nonce'])), 'publishpress_notif_save_metabox')) {
            return;
        }
        $key = isset($_POST['publishpress_notif'][$this->step_name . '_filters']['meta_key'])
            ? sanitize_text_field(wp_unslash($_POST['publishpress_notif'][$this->step_name . '_filters']['meta_key'])) : '';
        update_post_meta($id, static::META_KEY, $key);
    }

    public function get_run_workflow_query_args($query_args, $event_args)
    {
        $query_args['meta_query'][] = ['key' => static::META_KEY, 'value' => $event_args['params']['meta_key'] ?? '', 'compare' => '='];
        return $query_args;
    }
}
